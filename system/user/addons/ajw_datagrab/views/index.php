		<div class="box mb">
			<div class="tbl-ctrls">
				<h1>Create a new import</h1>

<?php 
	echo form_open( $form_action ); 
	echo form_hidden( "datagrab_step", "index" ); 
?>

<p>
	<select name="type">
<?php foreach( $types as $type => $type_label ): ?>
		<option value="<?php echo $type; ?>"><?php echo $type_label ?></option>
<?php endforeach; ?>
	</select>

	<input type="submit" value="Create new import" class="btn action" />
</p>

<?php echo form_close(); ?>

<p><a href="http://brandnewbox.co.uk/products/datatypes">Download additional datatypes &raquo;</a><br/><br/></p>

<?php if ( count( $saved_imports ) ): ?>

	</div>
	</div>

		<div class="box mb">
			<div class="tbl-ctrls">

<h1>Use a saved import</h1>

<?php echo form_open( $form_action ); ?>

<div class="tbl-wrap">
<?php

$this->table->set_template($cp_table_template);
$this->table->set_heading('ID', 'Name', 'Type', 'Options', "Status", 'Last run', '');

echo $this->table->generate($saved_imports);

?>
</div>

<p class="info">
	<strong>Saved imports</strong> can be run from outside the 
	Control Panel (eg, using a cron job), using the <em>Import URL</em></p>
<p>
	Copy the <em>Import URL</em> by right-clicking on the link and selecting 
	"Copy Link" (or similar).
</p>

<?php echo form_close(); ?>

<?php endif; ?>

			</div>
		</div>

