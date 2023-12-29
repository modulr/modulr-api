<html>
    <head>
        <meta charset="utf-8">
        <title>Checkout</title>
        <script type="module" src="https://assets.conekta.com/component/4.0.1/assets/component.min.js"></script>
      <!-- 	el script init-iframe.js almacena la configuración, 
			puedes verlo en la siguiente pestaña de código	-->
      
      	<script type="module" crossorigin defer>
            const config = {
            targetIFrame: "conektaIframeContainer",
            checkoutRequestId: "{{ $checkout_id }}",
            publicKey: "key_GfVkRxeDSX0qV3zQyWFTfHj",
            locale: "es",
            };
            const callbacks = {
            onFinalizePayment: (event) => console.log(event),
            onErrorPayment: (event) => console.log(event),
            onGetInfoSuccess: (event) => console.log(event),
            }

            window.ConektaCheckoutComponents.Integration({ config, callbacks });
        </script>
      
      <!-- 	es importante el atributo defer del script, 
			este permitirá que se cargue primero
			el archivo js del component y luego se ejecute el init	-->
    </head>
    <body>
    <div id="conektaIframeContainer" style="height: 700px;"></div>
    </body>
</html>