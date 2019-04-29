<div class="box">
    <?=form_open($baseUrl, 'class="tbl-ctrls"')?>
    	<fieldset class="tbl-search right">
    		<a class="btn tn action" href="<?=ee('CP/URL', 'addons/settings/tagger/create-group')?>"><?=lang('tagger:create_group')?></a>
    	</fieldset>

    	<h1><?=lang('tagger:groups')?></h1>

        <?=ee('CP/Alert')->get('groups-table')?>
    	<?php $this->embed('ee:_shared/table', $table); ?>
        <?php if ( ! empty($table['columns']) && ! empty($table['data'])): ?>
        <fieldset class="tbl-bulk-act hidden">
            <select name="bulk_action">
                <option value="">-- <?=lang('with_selected')?> --</option>
                <option value="remove" data-confirm-trigger="selected" rel="modal-confirm-remove"><?=lang('remove')?></option>
            </select>
            <input class="btn submit" data-conditional-modal="confirm-trigger" type="submit" value="<?=lang('submit')?>">
        </fieldset>
        <?php endif; ?>

    <?=form_close();?>
</div>

<?php
$modal_vars = array(
    'name'      => 'modal-confirm-remove',
    'form_url'  => $baseUrl.'/remove-group',
    'hidden'    => array(
        'bulk_action'   => 'remove'
    )
);

$modal = $this->make('ee:_shared/modal_confirm_remove')->render($modal_vars);
ee('CP/Modal')->addModal('remove', $modal);
?>