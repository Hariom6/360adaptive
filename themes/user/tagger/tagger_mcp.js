// ********************************************************************************* //
var Tagger = Tagger ? Tagger : new Object();
Tagger.prototype = new Object();
//********************************************************************************* //

$(document).ready(function() {

	if (jQuery('.TaggerTable').length > 0){
		Tagger.InitDataTable();
	}

	//$('.TaggerGroupSelect').multiSelect(null, Tagger.TaggerGroupSelect);
	$('.TaggerTable').on('click', '.DelTag', Tagger.DelConfirm);
	$('.TaggerTable').on('click', '.EditTag',Tagger.EditTag);
	$('.merge_tags').on('click', '.btn', Tagger.mergeTags);

});

//********************************************************************************* //

Tagger.InitDataTable = function(){

	Tagger.DataTables = jQuery('.TaggerTable').dataTable({
		bJQueryUI: false,
		sPaginationType: 'full_numbers', // Number pagination
		sDom: '<"toptable"lf>t<"bottomtable" ip>',
		bServerSide: true,
		sAjaxSource: Tagger.AJAX_URL,
		fnServerData: function ( sSource, aoData, fnCallback ) {
			aoData.push( {name: "ajax_method", value: "tags_dt" } );
			aoData.push( {name: "ee_base", value: EE.BASE} );
			jQuery.ajax({dataType:'json', type:"POST", url:sSource, data:aoData, success:fnCallback});
		},
		fnDrawCallback: function(){
			$('.gSel').multiSelect(null, Tagger.TaggerGroupSelect);
		}
	});

};

//********************************************************************************* //

Tagger.DelConfirm = function(event){
	var answer = confirm('Are you sure you want to delete this? All associated data will also be removed');

	if (answer){
		var Parent = $(event.target).closest('td');
		var TagID =  Parent.closest('tr').find('td:first').text();

		// Grab list of group id's and shoot AJAX
		var Params = {ajax_method: 'del_tag', tag_id: TagID};

		$.post(Tagger.AJAX_URL, Params, function(resData){

		});

		Parent.closest('tr').fadeOut('slow');
	}


	return false;
}

//********************************************************************************* //

Tagger.EditTag = function(event){

	var Parent = $(event.target).closest('td');
	var TagID =  Parent.closest('tr').find('td:first').text();
	var Tag =  Parent.closest('tr').find('td:eq(1)').text();

	var name = prompt("New Name", Tag);

	if (name!=null && name!="")
	{
		var Params = {ajax_method: 'edit_tag', tag_id: TagID, tag:name};

		$.post(Tagger.AJAX_URL, Params, function(resData){
			Tagger.DataTables.fnDraw();
		});
	}

	return false;
};

//********************************************************************************* //

Tagger.TaggerGroupSelect = function(Elem){

	var Parent = $(Elem).closest('td');
	var TagID =  Parent.closest('tr').find('td:first').text();

	// Create LoadingElement
	if (Parent.find('.LoadingIcon').length < 1)
		$('<a class="LoadingIcon" href="#" style="display: inline; margin: 0 0 0 10px;"></a>').appendTo(Parent);
	else
		Parent.find('.gIcon').removeClass('SuccessIcon').addClass('LoadingIcon');


	// Grab list of group id's and shoot AJAX
	var Params = {ajax_method: 'add_to_group', XID: Tagger.XID, groups:new Array(), tag_id: TagID};

	$(Parent).find('input[type=checkbox]:checked').not('.selectAll').each(function(){
		Params.groups.push($(this).val());
	});

	$.post(Tagger.AJAX_URL, Params, function(resData){
		Parent.find('.LoadingIcon').removeClass('LoadingIcon').addClass('SuccessIcon');
	});
};

//********************************************************************************* //

Tagger.mergeTags = function(evt){
	var target = $(evt.target);
	var parent = target.closest('.box');

	// Add "work" class to make the buttons pulsate
	// Update the button text to the value of its "work-text" data attribute
	target.addClass('work').attr('value', target.data('work-text'));

	var params = {
		ajax_method: 'merge_tags',
		tags: parent.find('input[name=tag_ids]').val()
	};

	$.post(Tagger.AJAX_URL, params, function(resData){
		parent.find('input[name=tag_ids]').val();
		Tagger.DataTables.fnDraw();

		target.removeClass('work').attr('value', target.data('submit-text'));
	});

	return false;
};

//********************************************************************************* //
