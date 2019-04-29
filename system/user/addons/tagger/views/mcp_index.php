<div class="box">
	<h1><?=lang('tagger:tags')?></h1>
	<div class="tbl-ctrls">
		<form>
			<table cellspacing="0" class="TaggerTable">
				<thead>
					<tr>
						<th style="width:50px" class="highlight"><?=lang('tagger:id')?></th>
						<th><?=lang('tagger:tag_name')?></th>
						<th style="width:80px"><?=lang('tagger:total_entries')?></th>
						<th><?=lang('tagger:groups')?></th>
						<th style="width:50px"><?=lang('tagger:action')?></th>
					</tr>
				</thead>
				<tbody>
				</tbody>
			</table>
		</form>
	</div>
</div>
<br>
<div class="box merge_tags">
	<h1><?=lang('tagger:merge_tags')?></h1>
	<div class="settings">
		<fieldset class="col-group">
			<div class="setting-txt col w-8">
				<h3><?=lang('tagger:tagids')?></h3>
				<em><?=lang('tagger:merge_exp')?></em>
			</div>
			<div class="setting-field col w-8 last">
				<input name="tag_ids" type="text">
			</div>
		</fieldset>
		<fieldset class="form-ctrls">
			<input class="btn" type="submit" value="<?=lang('tagger:merge_tags')?>" data-submit-text="<?=lang('tagger:merge_tags')?>" data-work-text="Merging...">
		</fieldset>
	</div>
</div>