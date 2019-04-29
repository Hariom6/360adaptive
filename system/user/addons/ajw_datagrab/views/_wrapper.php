<style>
	td.box {
		white-space: normal;
		color: #718ea9;
		background-color: #f0f3f6;
	}
	.subtext {
		font-size: 12px;
		margin-top: 6px;
		color: #666;
	}
	.ajw_datagrab_error {
		color: #900;
	}
	.ajw_datagrab_required {
		color: #f60;
		font-weight: bold;
	}
	.ajw_datagrab_subtext {
		font-size: 12px;
		margin-top: 6px;
		color: #999;
	}
	.ajw_datagrab_help {
		font-size: 12px;		
		margin-top: 6px;
	}
</style>
<?php if( isset( $errors ) && count( $errors ) ) {
	foreach( $errors as $error ) {
		echo '<p class="notice">Error: ' . $error . '</p>';
	}
}
?>

<?php $this->view($content);