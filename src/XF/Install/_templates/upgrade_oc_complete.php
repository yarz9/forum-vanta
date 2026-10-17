<?php
	use XF\Install\App;

class_exists(App::class, false) || die('Invalid');

	$templater->setTitle('Upgrading...');
?>

<form action="index.php?upgrade/run" method="post" class="blockMessage js-autoSubmit">

	<div>
		Upgrading...
	</div>

	<div class="u-noJsOnly">
		<button accesskey="s" class="button js-submitButton">Proceed...</button>
	</div>

	<?php echo $templater->fnCsrfInput($templater, $null); ?>
</form>
