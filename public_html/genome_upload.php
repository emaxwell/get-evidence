<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genome uploaded";

$user = getCurrentUser();
$page_content = "";

include('xmlrpc/xmlrpc.inc');

// check that we have a file
if (isset($_POST['reprocess_genome_id'])) {
    $reprocess_genome_ID = $_POST['reprocess_genome_id'];
    $reprocess_type = 'full';
    if (@$_POST['reproc_type'] == 'getev') {
	$reprocess_type = 'getev';
    }
    $permname = $GLOBALS["gBackendBaseDir"] . "/upload/" . $reprocess_genome_ID . "/genotype";
    if (! file_exists($permname)) {
        if (file_exists ($permname . ".gz")) {
            $permname = $permname . ".gz";
        } elseif (file_exists ($permname . ".bz2")) {
            $permname = $permname . ".bz2";
        } elseif (file_exists ($permname . ".gff")) {
	    $permname = $permname . ".gff";
	} elseif (file_exists ($permname . ".gff.gz")) {
	    $permname = $permname . ".gff.gz";
	} elseif (file_exists ($permname . ".gff.bz2")) {
	    $permname = $permname . ".gff.bz2";
	}
    }
    if (file_exists($permname)) {
        $page_content .= "<P>Reprocessing data: " . $reprocess_genome_ID . "</P>\n";
        $page_content .= "<P>The <A href=\"genomes?display_genome_id=$reprocess_genome_ID\">existing results</A> will remain available until the new analysis is complete.</P>\n";
        send_to_server($permname, $reprocess_type);
    } else {
        $page_content .= "<P>Error! Sorry, for some reason we are unable to find the "
            . "original file for " . $reprocess_genome_ID . "</P>";
    }
} elseif (isset($_POST['delete_genome_id'])) {
    $delete_genome_id = $_POST['delete_genome_id'];
    if (!preg_match ('{^[0-9]+$}', $delete_genome_id)) {
	$page_content .= "<P>Invalid delete_genome_id supplied: $delete_genome_id</P>";
    } else {
	$shasum = theDb()->getOne
	    ("SELECT shasum FROM private_genomes WHERE private_genome_id=? AND oid=?",
	     array($delete_genome_id, $user['oid']));
	theDb()->query
	    ("DELETE FROM private_genomes WHERE private_genome_id=? AND oid=?", 
	     array ($delete_genome_id, $user['oid']));
	$keeping_data = theDb()->getOne ("SELECT 1 FROM private_genomes WHERE shasum=? LIMIT 1",
					 array($shasum));
	if ($keeping_data) {
	    $page_content .= "<P>This entry has been removed from your \"uploaded genomes\" list, but the underlying data has not been deleted because it is referenced by other processing jobs.  Either you uploaded it more than once, or another user uploaded an identical file.</P>";
	} else {
	    $dir1 = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum;
	    $dir2 = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out";
	    if (delete_directory($dir2) &&
		delete_directory($dir1)) {
		$page_content .= "<P>Data and results for this job (input data hash $shasum) have been removed.</P>";
	    } else {
		$page_content .= "<P><B>OOPS.</B>  For some reason we are unable to delete your data.</P><P>Please <B>save a copy</B> of this hash: $shasum.</P><P>This may help the site admin track down and fix the problem.</P>";
		error_log ("Failed to delete id=$delete_genome_id sha1=$shasum");
	    }
	}
    }
} elseif(isset($_POST['nickname'])) {
    // If we are uploading and there is trio data, deal with parent genomes first
    $json_data = array();
    if (isset($_POST['trio'])) {
        $trio_type = $_POST['trio'];
        if ($trio_type === "child") {
            $child = $_POST['nickname'];
            $parA = $_POST['parent_a'];
            $parB = $_POST['parent_b']; 
            $shasumA = false;    
            if ($parA === "new") {
                if ((!empty($_FILES["genotype_parA"])) && (($_FILES['genotype_parA']['error'] == 2) || $_FILES['genotype_parA']['error'] == 3)) {
                    $page_content .= "Error: First parent file too large! Size limit is 500MB.";
                }
                elseif ((!empty($_FILES["genotype_parA"])) && ($_FILES['genotype_parA']['error'] == 0)) {
                    $shasumA = genotype_file_upload($_FILES["genotype_parA"], $user, $page_content, $_POST['nickname_parA']);
                }
                elseif (isset($_POST['location_parA']) && $user && $user['oid']) {
                    $shasumA = location_upload($_POST['location_parA'], $user, $page_content, $_POST['nickname_parA']);
                }
                else {
                    $page_content .= "Error: No file uploaded or file size exceeds limit.";
                }
                if (!$shasumA) {
                    $page_content .= " (first parent)";
                }
            }
            elseif ($parA === "none") {
                $page_content .= "Error: At least one parent genome must be selected or uploaded!";
	            header ("Location: genomes");
                exit;
            }
            else {
                //Selected an already uploaded genome
                $shasumA = theDb()->getOne
                    ("SELECT shasum FROM private_genomes WHERE nickname=?",
                    array($_POST['nickname_parA']));
            }

            $shasumB = false;    
            if ($parB === "new") {
                if ((!empty($_FILES["genotype_parB"])) && (($_FILES['genotype_parB']['error'] == 2) || $_FILES['genotype_parB']['error'] == 3)) {
                    $page_content .= "Error: First parent file too large! Size limit is 500MB.";
                }
                elseif ((!empty($_FILES["genotype_parB"])) && ($_FILES['genotype_parB']['error'] == 0)) {
                    $shasumB = genotype_file_upload($_FILES["genotype_parB"], $user, $page_content, $_POST['nickname_parB']);
                }
                elseif (isset($_POST['location_parB']) && $user && $user['oid']) {
                    $shasumB = location_upload($_POST['location_parB'], $user, $page_content, $_POST['nickname_parB']);
                }
                else {
                    $page_content .= "Error: No file uploaded or file size exceeds limit.";
                }
                if (!$shasumB) {
                    $page_content .= " (second parent)";
                }
            }
            elseif ($parB != "none") {
                //Only using a single parent
                $shasumB = false;
            }
            else {
                //Selected an already uploaded genome
                $shasumB = theDb()->getOne
                    ("SELECT shasum FROM private_genomes WHERE nickname=?",
                    array($_POST['nickname_parB']));                
            }
            // Add to json_data array
            if ($shasumA) {
                $json_data['parent A'] = $shasumA;
            }
            if ($shasumB) {
                $json_data['parent B'] = $shasumB;
            }
        }
    }
    // now deal with the main (child) genome
    if ((!empty($_FILES["genotype"])) && (($_FILES['genotype']['error'] == 2) || $_FILES['genotype']['error'] == 3)) {
        $page_content .= "Error: Genome file too large! Size limit is 500MB.";
    }
    elseif ((!empty($_FILES["genotype"])) && ($_FILES['genotype']['error'] == 0)) {
        $shasum = genotype_file_upload($_FILES["genotype"], $user, $page_content, $_POST['nickname'], $json_data);
    }
    elseif (isset($_POST['location']) && $user && $user['oid']) {
        $shasum = location_upload($_POST['location'], $user, $page_content, $_POST['nickname'], $json_data);
    }
    else {
        $page_content .= "Error: No file uploaded or file size exceeds limit.";
    }
    if ($shasum) {
        header ("Location: genomes?display_genome_id=$shasum");
	    exit;
    }
} else {
    $page_content .= "Error: No file uploaded or file size exceeds limit";
}

function genotype_file_upload($genotype_file, $user, &$page_content, $nick, $json = NULL) {
    $filename = basename($genotype_file['name']);
    $ext = substr($filename, strrpos($filename, '.') + 1);
    if (($ext == "txt" || $ext == "gff" || $ext == "gz" || $ext == "bz2") && ($genotype_file["size"] < 524288000)) {
        $tempname = $genotype_file['tmp_name'];
        $shasum = sha1_file($tempname);
        $page_content .= "shasum is $shasum<br>";
        $permname = $GLOBALS["gBackendBaseDir"] . "/upload/$shasum/genotype";
        if ($ext == "gz") {
            $permname = $permname . ".gz";
        } elseif ($ext == "bz2") {
            $permname = $permname . ".bz2";
        }
   	    $already_have = (file_exists($permname) &&
			 sha1_file($permname) == $shasum);
        // Attempt to move the uploaded file to its new place
	    if ($already_have)
	        unlink ($tempname);
	    else
	        @mkdir ($GLOBALS["gBackendBaseDir"] . "/upload/$shasum");

        if (!is_null($json)) { // trio metadata exists
            // Add parent metadata in child directory
            $metafile = $GLOBALS["gBackendBaseDir"] . "/upload/$shasum/metadata.json";
            if (file_exists($metafile)) {
                $file_contents = file_get_contents($metafile, false);
                $json_array = json_decode($file_contents, true);
                foreach ($json_array as $key => $val) {
                    $json[$key] = $val;
                }
            }
            $handle = fopen($metafile, 'w');
            fwrite($handle, json_encode($json));
            fclose($handle);

            // Try to add child metadata in parent A directory
            if (array_key_exists('parent A', $json)) {
                $shasumA = $json['parent A'];
                $metafile = $GLOBALS["gBackendBaseDir"] . "/upload/$shasumA/metadata.json";
                if (file_exists($metafile)) {
                    $file_contents = file_get_contents($metafile, false);
                    $json_array = json_decode($file_contents, true);
                    if (array_key_exists('children', $json_array)) {
                        array_push($json_array['children'], $shasum);
                    }
                    else {
                        $json_array['children'] = array();
                        array_push($json_array['children'], $shasum);
                    }
                }
                $handle = fopen($metafile, 'w');
                fwrite($handle, json_encode($json));
                fclose($handle);
            }

            // Try to add child metadata in parent B directory
            if (array_key_exists('parent B', $json)) {
                $shasumB = $json['parent B'];
                $metafile = $GLOBALS["gBackendBaseDir"] . "/upload/$shasumB/metadata.json";
                if (file_exists($metafile)) {
                    $file_contents = file_get_contents($metafile, false);
                    $json_array = json_decode($file_contents, true);
                    if (array_key_exists('children', $json_array)) {
                        array_push($json_array['children'], $shasum);
                    }
                    else {
                        $json_array['children'] = array();
                        array_push($json_array['children'], $shasum);
                    }
                }
                $handle = fopen($metafile, 'w');
                fwrite($handle, json_encode($json));
                fclose($handle);
            }
        }

        if ($already_have || move_uploaded_file($tempname, $permname)) {
            $nickname = $nick;
            $oid = $user['oid'];
	        if (!$already_have)
	            send_to_server($permname);

            theDB()->query ("INSERT IGNORE INTO private_genomes SET
                                oid=?, nickname=?, shasum=?, upload_date=SYSDATE()",
                                array ($oid,$nickname,$shasum));
            return $shasum;
        } else {
            $page_content .= "Error: A problem occurred during file upload! ($nick)";
        }
    } else {
        $page_content .= "Error: Only .txt, .gff, .gz or .bz2 files under 500MB are accepted for upload ($nick)";
    }
    return false;
}

function location_upload($location, $user, &$page_content, $nick, $json = NULL) {
  $location = preg_replace('{/\.\./}','',$location); # No shenanigans
  if (preg_match('{^file:///}',$location)) {
    $location = preg_replace('{^file://}','',$location);
    if (file_exists($location) && strpos ($location, $GLOBALS["gBackendBaseDir"] . "/upload/") === 0) {
      $shasum = sha1_file($location);
      $permname = $GLOBALS["gBackendBaseDir"] . "/upload/$shasum/genotype";
      if (preg_match ('{\.gz$}', $location))
          $permname = $permname . ".gz";
      elseif (preg_match ('{\.bz2$}', $location))
	  $permname = $permname . ".bz2";
      // Attempt to move the uploaded file to its new place
      @mkdir ($GLOBALS["gBackendBaseDir"] . "/upload/$shasum");

        if (!is_null($json)) { // trio metadata exists
            // Add parent metadata in child directory
            $metafile = $GLOBALS["gBackendBaseDir"] . "/upload/$shasum/metadata.json";
            if (file_exists($metafile)) {
                $file_contents = file_get_contents($metafile, false);
                $json_array = json_decode($file_contents, true);
                foreach ($json_array as $key => $val) {
                    $json[$key] = $val;
                }
            }
            $handle = fopen($metafile, 'w');
            fwrite($handle, json_encode($json));
            fclose($handle);

            // Try to add child metadata in parent A directory
            if (array_key_exists('parent A', $json)) {
                $shasumA = $json['parent A'];
                $metafile = $GLOBALS["gBackendBaseDir"] . "/upload/$shasumA/metadata.json";
                $jsonA = array();
                if (file_exists($metafile)) {
                    $file_contents = file_get_contents($metafile, false);
                    $jsonA = json_decode($file_contents, true);
                }
                if (!array_key_exists('children', $jsonA)) {
                    $jsonA['children'] = array();
                }
                array_push($jsonA['children'], $shasum);
                $handle = fopen($metafile, 'w');
                fwrite($handle, json_encode($jsonA));
                fclose($handle);
            }

            // Try to add child metadata in parent B directory
            if (array_key_exists('parent B', $json)) {
                $shasumB = $json['parent B'];
                $metafile = $GLOBALS["gBackendBaseDir"] . "/upload/$shasumB/metadata.json";
                $jsonB = array();
                if (file_exists($metafile)) {
                    $file_contents = file_get_contents($metafile, false);
                    $jsonB = json_decode($file_contents, true);
                }
                if (!array_key_exists('children', $jsonB)) {
                    $jsonB['children'] = array();
                }
                array_push($jsonB['children'], $shasum);
                $handle = fopen($metafile, 'w');
                fwrite($handle, json_encode($jsonB));
                fclose($handle);
            }
        }

      $already_have = (file_exists($permname) &&
		       sha1_file($permname) == $shasum);
      if ($already_have || copy($location,$permname)) {
        $nickname = $nick;
        $oid = $user['oid'];
	    if (!$already_have)
	        send_to_server($permname);

        theDB()->query ("INSERT IGNORE INTO private_genomes SET
                            oid=?, nickname=?, shasum=?, upload_date=SYSDATE()",
                            array ($oid,$nickname,$shasum));
        return $shasum;
      } else {
        $page_content .= "Error: A problem occurred during file upload! ($nick)";
      }
    } else {
      $page_content .= "Error: file not found on local filesystem! ($nick)";
    }
  } else {
    $page_content .= "Error: Please use the file:/// syntax to refer to a local file! ($nick)";
  }
  return false;
}

// Send the filename to the xml-rpc server.
function send_to_server($permname, $type = "full") {
    $client = new xmlrpc_client("http://localhost:8080/");
    $client->return_type = 'phpvals';
    if ($type == "getev") {
	$message = new xmlrpcmsg("reprocess_getev", array(new xmlrpcval($permname, "string")));
    } else {
	$message = new xmlrpcmsg("submit_local", array(new xmlrpcval($permname, "string")));
    }
    $resp = $client->send($message);
    if ($resp->faultCode()) { error_log ("xmlrpc send Error: ".$resp->faultString()); }
    error_log ("xmlrpc send success: ".$resp->value());
    return true;
}

// Delete all files in a directory recursively, then delete directory
function delete_directory($dirname) {
    if (preg_match ('{/$}', $dirname)) // don't accidentally delete /foo/$ttypo
	return false;
    if (is_dir($dirname))
        $dir_handle = opendir($dirname);
    if (!$dir_handle)
        return false;
    while($file = readdir($dir_handle)) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dirname."/".$file))
                unlink($dirname."/".$file);
            else
                delete_directory($dirname.'/'.$file);    
        }
    }
    closedir($dir_handle);
    rmdir($dirname);
    return true;
}

$gOut["content"] = $page_content;

go();

?>
