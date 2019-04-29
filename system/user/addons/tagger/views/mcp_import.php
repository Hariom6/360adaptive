<div class="box">
	<h1><?=lang('tagger:import:text')?></h1>
    <?=form_open($baseUrl.'/do_import_text', 'class="settings"')?>

    <fieldset class="col-group">
        <div class="setting-txt col w-8">
            <h3><?=lang('tagger:import:source')?></h3>
        </div>
        <div class="setting-field col w-8 last">
            <?=form_dropdown('source', $fields_normal)?>
        </div>
    </fieldset>

    <fieldset class="col-group">
        <div class="setting-txt col w-8">
            <h3><?=lang('tagger:import:dest')?></h3>
        </div>
        <div class="setting-field col w-8 last">
            <?=form_dropdown('dest', $fields_tagger)?>
        </div>
    </fieldset>

    <fieldset class="col-group">
        <div class="setting-txt col w-8">
            <h3><?=lang('tagger:import:separator')?></h3>
        </div>
        <div class="setting-field col w-8 last">
            <?=form_input('sep', ',', 'style="width:50px"')?>
        </div>
    </fieldset>

    <fieldset class="form-ctrls">
        <input class="btn" type="submit" value="<?=lang('tagger:import')?>" data-submit-text="<?=lang('tagger:import')?>" data-work-text="importing...">
    </fieldset>

    <?=form_close()?>
</div>
<br>
<div class="box">
    <h1><?=lang('tagger:import:solspace')?></h1>
    <?=form_open($baseUrl.'/do_import_solspace', 'class="settings"')?>

    <?php if ($solspace_tags != FALSE):?>

    <fieldset class="col-group">
        <div class="setting-txt col w-8">
            <h3><?=lang('tagger:import:channel')?></h3>
        </div>
        <div class="setting-field col w-8 last">
            <ul class="ulcols">
                <?php foreach($channels AS $channel_id => $channel_title):?>
                <li><input name="channels[]" type="checkbox" value="<?=$channel_id?>" />&nbsp;&nbsp;<?=$channel_title?></li>
                <?php endforeach;?>
            </ul>
        </div>
    </fieldset>

    <fieldset class="form-ctrls">
        <input class="btn" type="submit" value="<?=lang('tagger:import')?>" data-submit-text="<?=lang('tagger:import')?>" data-work-text="importing...">
    </fieldset>

    <?php else:?>
        <?=lang('tagger:import:missing_solspace')?>
    <?php endif;?>

    <?=form_close()?>
</div>
<br>
<div class="box">
    <h1><?=lang('tagger:import:taggable')?></h1>
    <?=form_open($baseUrl.'/do_import_taggable', 'class="settings"')?>

    <?php if ($taggable_tags != FALSE):?>

    <fieldset class="col-group">
        <div class="setting-txt col w-8">
            <h3><?=lang('tagger:import:channel')?></h3>
        </div>
        <div class="setting-field col w-8 last">
            <ul class="ulcols">
                <?php foreach($channels AS $channel_id => $channel_title):?>
                <li><input name="channels[]" type="checkbox" value="<?=$channel_id?>" />&nbsp;&nbsp;<?=$channel_title?></li>
                <?php endforeach;?>
            </ul>
        </div>
    </fieldset>

    <fieldset class="form-ctrls">
        <input class="btn" type="submit" value="<?=lang('tagger:import')?>" data-submit-text="<?=lang('tagger:import')?>" data-work-text="importing...">
    </fieldset>

    <?php else:?>
        <?=lang('tagger:import:missing_taggable')?>
    <?php endif;?>

    <?=form_close()?>
</div>