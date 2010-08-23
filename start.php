<?php
  // register our overriding file upload action
  register_action("file/upload", false, $CONFIG->pluginspath . "file_multigroup_upload/actions/upload.php");
?>
