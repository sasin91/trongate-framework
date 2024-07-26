<?php
class Welcome extends Trongate {

	/**
	 * Renders the (default) homepage for public access.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->module('trongate_pages');
		$this->trongate_pages->display();
	}

    public function ehlo()
    {
        $this->module('cors');

        /** @var Cors $cors */
        $cors = $this->cors;

        $cors->setup(
            allowedOriginsString: 'http://localhost:3000',
            allowedMethodsString: 'GET, OPTIONS',
            allowedHeadersString: 'trongatetoken, X-Requested-With, Content-Type, Origin, Authorization, Accept, Client-Security-Token, Accept-Encoding',
            allowedCredentials: true
        );

        $cors->defineHeaders(origin: $_SERVER['HTTP_ORIGIN']);

        //api_auth();

        header('Content-Type: application/json');
        echo json_encode(['body' => '<section><h1>EHLO</h1><p>how r u</p></section>']);
    }

}