<?php
  /**
   * Group selection view -- lists all views a user has write access to with checkboxes
   * 
   * @package FileExtensions
   * @author Aaron Unger
   * @copyright The Concord Consortium 2010
   * @link http://www.concord.org/
   */
 
  // TODO there might be a more efficient way to find all the groups a user can upload files into
	function is_group_writable($group) {
		return can_write_to_container(get_loggedin_userid(),$group->guid,'object');
	}

	$groups = array_filter(elgg_get_entities(array('type' => 'group')), 'is_group_writable');
	$container = isset($vars['container']) ? $vars['container'] : null;
	
	function checked($container, $group) {
	  if ($container != null && $container->type == 'group') {
	    echo ($container->guid == $group->guid ? " checked='checked' " : "");
    }
	}

?>
<?php echo elgg_echo('file_multigroup_upload:group_selection'); ?>
<div class='categories'>
  <p>
  <?php foreach($groups as $group) { ?>
  <label>
    <input class="input-checkboxes" type="checkbox" name="group_selection[]" value="<?php echo $group->guid; ?>" <?php checked($container, $group) ?>/><?php echo $group->name; ?>
  </label>
  <br />
  <?php } ?>
  </p>
</div>
