<section id="content">
  <h1>welcome, welcome</h1>
  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="flip">Flip</button>
  <button onclick="window.location.reload();void(0);">Reload</button>
  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="pop">Pop</button>
  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="push">Push</button>
  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="flow">Flow</button>
  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="slide-up">Slide up</button>
  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="slide-down">Slide down</button>

  <button mx-get="/welcome/flip" mx-target="#content" mx-swap="outerHTML" mx-select="#content" mx-transition="fade-slide">Fade & slide (custom one)</button>
</section>
