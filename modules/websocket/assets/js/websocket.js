function debounce(func, wait) {
    let timeout;

    return function(...args) {
        const context = this;

        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

function extractFingerprintData() {
    const fingerprintData = [];

    fingerprintData.push(navigator.userAgent);
    fingerprintData.push(`${screen.width}x${screen.height}`);
    fingerprintData.push(screen.colorDepth);
    fingerprintData.push(Intl.DateTimeFormat().resolvedOptions().timeZone);
    fingerprintData.push(navigator.language);

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    ctx.textBaseline = 'top';
    ctx.font = '16px Arial';
    ctx.fillStyle = '#f60';
    ctx.fillRect(0, 0, 100, 100);
    ctx.fillStyle = '#069';
    ctx.fillText('Browser fingerprinting', 2, 12);
    ctx.strokeStyle = '#ff0000';
    ctx.arc(50, 50, 50, 0, Math.PI * 2, true);
    ctx.stroke();
    const canvasData = canvas.toDataURL();
    fingerprintData.push(canvasData);

    const gl = canvas.getContext('webgl');
    if (gl) {
        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        fingerprintData.push(gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL));
        fingerprintData.push(gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL));
    }

    return fingerprintData;
}

async function generateFingerprint() {
    const fingerprintBuffer = await crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode(
            extractFingerprintData().join('::')
        )
    );

    return Array.from(new Uint8Array(fingerprintBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
}

class Socket {
    /**
     * @type {URL}
     */
    #url = null;
    #callbacks = {
        onopen: [],
        onmessage: [],
        onerror: [],
        onclose: [],
        state_change: []
    };
    connected = false;
    instance = null;
    state = undefined;
    flags = {
        reconnect_on_close: true
    }

    constructor(address) {
        const url = new URL(address);

        generateFingerprint().then((fingerprint) => {
            url.searchParams.set('fingerprint', fingerprint);

            this.#url = url;

            this.init();
        });

        const self = this;

        const state = {
            num_online: 0,
        }

        this.state = new Proxy({ value: state }, {
            set(target, key, value) {
                const original = target[key];
                const changes = Object.keys(value).filter(prop => original[prop] !== value[prop]);

                const ret = Reflect.set(...arguments);

                for (const handler of self.#callbacks.state_change) {
                    handler({ changes, original, value }, target);
                }

                return ret;
            },

            get() {
                return Reflect.get(...arguments);
            }
        });

    }

    reconnect() {
        debounce(this.init, 1000);
    }

    init() {
        this.instance = new WebSocket(this.#url);

        this.instance.onopen = (event) => {
            this.connected = true;

            for (const handler of this.#callbacks.onopen) {
                handler(event);
            }
        };

        this.instance.onclose = (event) => {
            this.connected = false;

            if (this.flags.reconnect_on_close) {
                this.reconnect();
            }

            for (const handler of this.#callbacks.onclose) {
                handler(event);
            }
        };

        this.instance.onerror = (event) => {
            for (const handler of this.#callbacks.onerror) {
                handler(event);
            }
        };

        this.instance.onmessage = (event) => {
            const data = JSON.parse(event.data);

            switch(data.channel) {
                case 'state':
                    this.state.value = data.message;
                break;
            }

            for (const handler of this.#callbacks.onmessage) {
                handler(data, event);
            }
        };

        document.dispatchEvent(new Event('websocket:init.done', { bubbles: true }));
    }

    /**
     * Register an event listener on the socket.
     *
     * @param {String} event
     * @param {(event) => void} handler
     */
    addEventListener(event, handler) {
        const key = event in this.#callbacks ? event : `on${event}`;

        this.#callbacks[key].push(handler);
    }

    /**
     * Convenient wrapper for addEventListener(`state_change`, ...)
     *
     * @param {String | Function} key
     * @param {({ key, value, original }) => void | undefined} callback
     */
    onStateChange(key, callback = undefined) {
        if (!callback) {
            this.addEventListener('state_change', key);
        } else {
            this.addEventListener('state_change', (state) => {
                if (state.changes.includes(key)) {
                    callback(state.value[key]);
                }
            })
        }
    }

    /**
     * Convenient wrapper for addEventListener(`message`, ...) with channel filtering.
     *
     * @param {String} channel
     * @param {(message) => void} callback
     * @returns {void}
     */
    onMessage(channel, callback) {
      this.addEventListener('message', (data) => {
        if (data.channel === channel) {
          callback(data.message);
        }
      });
    }

    setUrlParam(key, value = undefined) {
        if (Array.isArray(key)) {
            key.forEach((v, k) => {
                this.#url.searchParams.set(k, v);
            });
        } else {
            this.#url.searchParams.set(key, value);
        }

        const original_reconnect_flag = this.flags.reconnect_on_close;
        this.flags.reconnect_on_close = false;

        try {
            this.init();
        } finally {
            this.flags.reconnect_on_close = original_reconnect_flag;
        }
    }

    send(message) {
        return this.instance.send(message);
    }
}
