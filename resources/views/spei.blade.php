<html>
	<head>
		<link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,700" rel="stylesheet">
		<style>
			/* Reset -------------------------------------------------------------------- */
			* 	 { margin: 0;padding: 0; }
			body { font-size: 14px; }

			/* PS ----------------------------------------------------------------------- */

			h3 {
				margin-bottom: 10px;
				font-size: 15px;
				font-weight: 600;
				text-transform: uppercase;
			}

			.ps {
				width: 496px; 
				border-radius: 4px;
				box-sizing: border-box;
				padding: 0 45px;
				margin: 40px auto;
				overflow: hidden;
				border: 1px solid #b0afb5;
				font-family: 'Open Sans', sans-serif;
				color: #4f5365;
			}

			.ps-reminder {
				position: relative;
				top: -1px;
				padding: 9px 0 10px;
				font-size: 11px;
				text-transform: uppercase;
				text-align: center;
				color: #ffffff;
				background: #000000;
			}

			.ps-info {
				margin-top: 26px;
				position: relative;
			}

			.ps-info:after {
				visibility: hidden;
				display: block;
				font-size: 0;
				content: " ";
				clear: both;
				height: 0;
			}

			.ps-brand {
				width: 45%;
				float: left;
			}

			.ps-brand img {
				max-width: 150px;
				margin-top: 2px;
			}

			.ps-amount {
				width: 55%;
				float: right;
			}

			.ps-amount h2 {
				font-size: 36px;
				color: #000000;
				line-height: 24px;
				margin-bottom: 15px;
			}

			.ps-amount h2 sup {
				font-size: 16px;
				position: relative;
				top: -2px
			}

			.ps-amount p {
				font-size: 10px;
				line-height: 14px;
			}

			.ps-reference {
				margin-top: 14px;
			}

			h1 {
				font-size: 27px;
				color: #000000;
				text-align: center;
				margin-top: -1px;
				padding: 6px 0 7px;
				border: 1px solid #b0afb5;
				border-radius: 4px;
				background: #f8f9fa;
			}

			.ps-instructions {
				margin: 32px -45px 0;
				padding: 32px 45px 45px;
				border-top: 1px solid #b0afb5;
				background: #f8f9fa;
			}

			ol {
				margin: 17px 0 0 16px;
			}

			li + li {
				margin-top: 10px;
				color: #000000;
			}

			a {
				color: #1475ce;
			}

			.ps-footnote {
				margin-top: 22px;
				padding: 22px 20 24px;
				color: #108f30;
				text-align: center;
				border: 1px solid #108f30;
				border-radius: 4px;
				background: #ffffff;
			}
		</style>
	</head>
	<body>
		<div class="ps">
			<div class="ps-header">
				<div class="ps-reminder">Ficha digital. No es necesario imprimir.</div>
				<div class="ps-info">
					<div class="ps-brand"><img src="https://github.com/conekta-examples/spei-payment-stub/blob/master/demo/spei_brand.png?raw=true" alt="Banorte"></div>
					<div class="ps-amount">
						<h3>Monto a pagar</h3>
						<h2>${{$order['amount']}} <sup>MXN</sup></h2>
						<p>Utiliza exactamente esta cantidad al realizar el pago.</p>
					</div>
				</div>
				<div class="ps-reference">
					<h3>CLABE</h3>
					<h1>{{$order['charges']['data'][0]['payment_method']['clabe']}}</h1>
				</div>
			</div>
			<div class="ps-instructions">
				<h3>Instrucciones</h3>
				<ol>
					<li>Accede a tu banca en línea.</li>
					<li>Da de alta la CLABE en esta ficha. <strong>El banco deberá de ser STP</strong>.</li>
					<li>Realiza la transferencia correspondiente por la cantidad exacta en esta ficha, <strong>de lo contrario se rechazará el cargo</strong>.</li>
					<li>Al confirmar tu pago, el portal de tu banco generará un comprobante digital. <strong>En el podrás verificar que se haya realizado correctamente.</strong> Conserva este comprobante de pago.</li>
				</ol>
				<div class="ps-footnote">Al completar estos pasos recibirás un correo de <strong>Nombre del negocio</strong> confirmando tu pago.</div>
			</div>
		</div>	
	</body>
</html>