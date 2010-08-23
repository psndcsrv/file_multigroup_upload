<?php
	/**
	 * Elgg file browser uploader/edit action
	 * 
	 * @package ElggFile
	 * @author Curverider Ltd
	 * @copyright Curverider Ltd 2008-2010
	 * @link http://elgg.com/
	 * 
	 * 2010-08-20 This file was modified by Aaron Unger of The Concord Consortium to support uploading a file into multiple groups at one time.
	 */

	global $CONFIG;
	
	gatekeeper();
	
	// Get variables
	$title = get_input("title");
	$desc = get_input("description");
	$access_id = (int) get_input("access_id");

	$original_guid = (int) get_input('file_guid');
	$tags = get_input("tags");

	// check whether this is a new file or an edit
	$new_file = true;
	if ($original_guid > 0) {
		$new_file = false;
	}
	
	$context_container_guid = (int) get_input('container_guid', 0);
	if ($context_container_guid == 0) {
		$context_container_guid = get_loggedin_userid();
	}
	
	function create_file($container_guid, $title, $desc, $access_id, $guid, $tags, $new_file) {
	  // register_error("Creating file: " . $container_guid . ", vars: " . print_r(array($title, $desc, $access_id, $guid, $tags, $new_file), true));
  	if ($new_file) {
  		// must have a file if a new file upload
  		if (empty($_FILES['upload']['name'])) {
  			// cache information in session
  			$_SESSION['uploadtitle'] = $title;
  			$_SESSION['uploaddesc'] = $desc;
  			$_SESSION['uploadtags'] = $tags;
  			$_SESSION['uploadaccessid'] = $access_id;
			
  			register_error(elgg_echo('file:nofile') . "no file new");
  			forward($_SERVER['HTTP_REFERER']);
  		}
		
  		$file = new FilePluginFile();
  		$file->subtype = "file";
		
  		// if no title on new upload, grab filename
  		if (empty($title)) {
  			$title = $_FILES['upload']['name'];
  		}
	
  	} else {
  		// load original file object
  		$file = get_entity($guid);
  		if (!$file) {
  			register_error(elgg_echo('file:cannotload') . 'can"t load existing');
  			forward($_SERVER['HTTP_REFERER']);
  		}
		
  		// user must be able to edit file
  		if (!$file->canEdit()) {
  			register_error(elgg_echo('file:noaccess') . 'no access to existing');
  			forward($_SERVER['HTTP_REFERER']);
  		}
  	}
	
  	$file->title = $title;
  	$file->description = $desc;
  	$file->access_id = $access_id;
  	$file->container_guid = $container_guid;
	
  	$tags = explode(",", $tags);
  	$file->tags = $tags;
	
  	// we have a file upload, so process it
  	if (isset($_FILES['upload']['name']) && !empty($_FILES['upload']['name'])) {
		
  		$prefix = "file/";
		
  		// if previous file, delete it
  		if ($new_file == false) {
  			$filename = $file->getFilenameOnFilestore();
  			if (file_exists($filename)) {
  				unlink($filename);
  			}

  			// use same filename on the disk - ensures thumbnails are overwritten
  			$filestorename = $file->getFilename();
  			$filestorename = elgg_substr($filestorename, elgg_strlen($prefix));
  		} else {
  			$filestorename = elgg_strtolower(time().$_FILES['upload']['name']);
  		}
		
  		$file->setFilename($prefix.$filestorename);
  		$file->setMimeType($_FILES['upload']['type']);
  		$file->originalfilename = $_FILES['upload']['name'];
  		$file->simpletype = get_general_file_type($_FILES['upload']['type']);
	
  		$file->open("write");
  		$file->write(get_uploaded_file('upload'));
  		$file->close();
		
  		$guid = $file->save();
		
  		// if image, we need to create thumbnails (this should be moved into a function)
  		if ($guid && $file->simpletype == "image") {
  			$thumbnail = get_resized_image_from_existing_file($file->getFilenameOnFilestore(),60,60, true);
  			if ($thumbnail) {
  				$thumb = new ElggFile();
  				$thumb->setMimeType($_FILES['upload']['type']);
				
  				$thumb->setFilename($prefix."thumb".$filestorename);
  				$thumb->open("write");
  				$thumb->write($thumbnail);
  				$thumb->close();
				
  				$file->thumbnail = $prefix."thumb".$filestorename;
  				unset($thumbnail);
  			}
			
  			$thumbsmall = get_resized_image_from_existing_file($file->getFilenameOnFilestore(),153,153, true);
  			if ($thumbsmall) {
  				$thumb->setFilename($prefix."smallthumb".$filestorename);
  				$thumb->open("write");
  				$thumb->write($thumbsmall);
  				$thumb->close();
  				$file->smallthumb = $prefix."smallthumb".$filestorename;
  				unset($thumbsmall);
  			}
			
  			$thumblarge = get_resized_image_from_existing_file($file->getFilenameOnFilestore(),600,600, false);
  			if ($thumblarge) {
  				$thumb->setFilename($prefix."largethumb".$filestorename);
  				$thumb->open("write");
  				$thumb->write($thumblarge);
  				$thumb->close();
  				$file->largethumb = $prefix."largethumb".$filestorename;
  				unset($thumblarge);
  			}
  		}
  	} else {
  		// not saving a file but still need to save the entity to push attributes to database
  		$file->save();
  	}
	  return array($file,$guid);
  }
  
  $container_guids = get_input("group_selection", array(), false);
  if (! is_array($container_guids)) { $container_guids = array_filter(array($container_guids)); }
  if (! in_array($context_container_guid, $container_guids)) {
    array_unshift($container_guids, $context_container_guid);
  }
  
  // register_error("Container guids: " . print_r($container_guids, true) . ", raw: " . print_r($_REQUEST["group_selection"], true));
  
  $file = null;
  foreach ($container_guids as $container_guid) {
    list($file,$guid) = create_file($container_guid, $title, $desc, $access_id, $original_guid, $tags, $new_file);
    
    // make sure session cache is cleared
  	unset($_SESSION['uploadtitle']);
  	unset($_SESSION['uploaddesc']);
  	unset($_SESSION['uploadtags']);
  	unset($_SESSION['uploadaccessid']);

  	// handle results differently for new files and file updates
  	if ($new_file) {
  		if ($guid) {
  			system_message(elgg_echo("file:saved"));
  			add_to_river('river/object/file/create', 'create', get_loggedin_userid(), $file->guid);
  		} else {
  			// failed to save file object - nothing we can do about this
  			register_error(elgg_echo("file:uploadfailed") . "new");
  		}
  	} else {
  		if ($guid) {
  			system_message(elgg_echo("file:saved"));
  		} else {
  			register_error(elgg_echo("file:uploadfailed") . "existing");
  		}
  	}
  }
  
  // forward the user onward
  if ($new_file) {
    $container_user = get_entity($context_container_guid);
		forward($CONFIG->wwwroot . "pg/file/" . $container_user->username);
	} else {
	  forward($file->getURL());
	}
?>