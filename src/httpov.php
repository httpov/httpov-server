<?php
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

$output_dir = "../safe_directory/";
$use_gui = 1;
$use_syslog = 1;

require "../httpov_conf.php";

if($use_syslog) {
  openlog("httpov", LOG_PID | LOG_PERROR, LOG_LOCAL0);
}

$version = "0.8";
$required_client = "0";
$recommended_client = "2";

$db["link"] = mysql_connect ( $db["server"], $db["mortal"],
                              $db["mortal_pass"] );

// At the moment, there are two branches of HTTPov clients,
// the bash client and the Python one. When only the bash client
// was in use, the HTTPov client version numbering system was
// straightforward; Version 1, revision 0, and so on.
//
// The Python client made it necessary to repurpose the first
// digit, as a technology enumerator. 1.x is bash, 2.x is Python,
// and the .x indicates "feature level".
//
// Bug fixes that does not introduce new features will be indicated
// by a third digit.

$used_client = explode(".", $_GET["version"], 2);

function log_state($state=NULL) {
  global $use_gui;
  if($use_gui) {
    $tm     = date('Y-m-d H:i:s',time());
    $ip     = mysql_real_escape_string(getenv('REMOTE_ADDR'));
    $ver    = mysql_real_escape_string($_GET["version"]);
    if($state === NULL) {
      $cmd    = mysql_real_escape_string($_GET["command"]);
    } else {
      $cmd = $state;
    }
    $client = mysql_real_escape_string($_GET["client"]);

    $query = "REPLACE INTO track(tm, ip, ver, cmd, client) VALUES ('$tm','$ip','$ver','$cmd','$client');";

    mysql_query($query);
  }
}

function hpsyslog($type, $data) {
  global $use_syslog;
  if($use_syslog) {
    syslog($type, $data);
  }
}

if(version_compare($used_client[1], $required_client, "<") || $used_client[0] === "0") {
  echo "command=sleep\n";
  echo "message=HTTPov client ".$used_client[0].".".$required_client." or later required.\n";
  //echo "message=Just messing around, no new client available.\n";
  die();
}

mysql_select_db ( $db["db"], $db["link"] );

log_state();

switch ($_GET["command"]) {
  case "hello":
    echo "message=HTTPov server version ".$version." ready.\n";
  break;
  case "getjob":
    $sql = "select * from job where finished='0' and aborted='0' order by id desc";
    $result = mysql_query($sql);

    if(mysql_num_rows($result)) {

      $findjob = 1;
      $jobfound = 0;
      while($findjob) {
        if($row = mysql_fetch_array($result)) {
          if($row["clients"]) {
            $sql = "select count(*) as clients from batch where (issued between ".(time()-65)." and ".time()." or active between ".(time()-65)." and ".time().") and job=".$row["id"];
            $result2 = mysql_query($sql);
            $row2 = mysql_fetch_array($result2);
            if($row2["clients"] < $row["clients"]) {
              $findjob = 0;
            }
          } else {
            $findjob = 0;
          }
        } else {
          $findjob = 0;
        }
      }

      if(is_array($row)) {
        echo "command=getbatch\n";
        echo "job=".$row["id"]."\n";
        echo "name=".$row["name"]."\n";
        echo "frames=".$row["frames"]."\n";
      } else {
        echo "command=sleep\n";
        log_state("sleep");
      }
    } else {
      echo "command=sleep\n";
      log_state("sleep");
    }
    if(version_compare($used_client[1], $recommended_client, "<")) {
      echo "message=HTTPov client ".$used_client[0].".".$recommended_client." or later recommended.\n";
    }
  break;
  case "getbatch":
    if(!array_key_exists("job", $_GET) || !array_key_exists("client", $_GET)) {
      echo "error=".__LINE__."\n";
      die();
    }
    $job = mysql_real_escape_string($_GET["job"]);
    $client = explode(":", mysql_real_escape_string($_GET["client"]));
    if(array_key_exists("cgroup", $_GET)) {
      $cgroup = mysql_real_escape_string($_GET["cgroup"]);
    } else {
      $cgroup = $client[0];
    }

    $locked = 0;
    $retry = 3;
    while(!$locked && $retry) {
      $sql = "update job set locked=".time()." where locked=0 and ".
             "finished=0 and aborted=0 and id='".$job."'";
      $result = mysql_query($sql);
      $locked = mysql_affected_rows();
      // If lock succeeded, waste no time.
      if(!$locked) {
        sleep(1);
      }
      $retry--;
    }

    if($locked) {
      $skipbatch = 0;
      $uploaded = 0;
      $sql = "select * from job where id='".$job."'";
      $result = mysql_query($sql);
      $row = mysql_fetch_array($result);

      $startframe = $row["current"];

// Todo:
// Unify the sliced/unsliced code.

      if($row["sliced"]) {
        $stopframe = $startframe;
        if(($row["current"] + 1 ) == $row["frames"] &&
           ($row["slice"] * $row["count"] + 1) > $row["rows"]) {

          // The last batch has been issued.
          // (We're on the last frame, and the start of
          // this slice would be larger than rows.)
          // Look for the first unfinished batch (which may be the last
          // batch), and re-issue it to the client. This will have
          // the effect that several clients may be working in parallel
          // at finishing the last and any aborted batches*. The first to
          // finish wins.

	  // * If the number of clients exceed the number of unfinished
	  // or aborted batches.

          $slice = $row["slice"];

          $sql2 = "select * from batch where job='".$job."'".
                  " and finished='0' limit 1";
          $result2 = mysql_query($sql2);
          if(mysql_num_rows($result2)) {

            $row2 = mysql_fetch_array($result2);

            $slice = $row2["slice"]; // Set slice to the found batch's slice,
                                     // instead of the job slice counter,
                                     // as that is wrong now.

            if(file_exists($_SERVER["DOCUMENT_ROOT"].$output_dir.
                           $row["name"]."/".$row["name"]."_frame_".
                           (str_pad($row2["frame"], 8, "0", STR_PAD_LEFT)).
                           ($row["sliced"]?("_".str_pad($row2["slice"], 4, "0", STR_PAD_LEFT)):"").
                           ".zip")) {
              $uploaded = 1;
            }
            if($row2["aborted"]) {
              $aborted = "";
            } else {
              $aborted = ", aborted='".time()."' ";
            }
            $sql3 = "update batch set finished='".$row2["issued"]."'".
                    $aborted." where id='".$row2["id"]."'";
            mysql_query($sql3);

            $startframe = $row2["frame"];
            $stopframe = $row2["frame"];

            $startrow = $row2["slice"] * $row["count"] + 1;
            $endrow = ($row2["slice"] + 1) * $row["count"];

            $sql = "update job set locked=0 ".
                   "where id='".$job."'";
          } else {

            // All batches have been issued, and all batches have been
            // finished. Nothing more to do; mark job as finished.

// Todo:
// Check if all batches really are uploaded.
// If they are, mark job finished.
// If not, reissue those not uploaded.

            $sql = "update job set locked=0, finished='".time()."' ".
                   "where id='".$job."'";
            $skipbatch = 1;
          }

        } else {
          // current has not yet reached frames
          // there are plenty of batches left
          // just issue one

          $startrow = $row["slice"] * $row["count"] + 1;
          $endrow = ($row["slice"] + 1) * $row["count"];
          if($endrow >= $row["rows"]) {
            $endrow = $row["rows"];
            if(($row["current"]+1) != $row["frames"]) {
              $slice = $row["slice"];//0;
              $sql = "update job set current='".($row["current"]+1).
                     "', slice=0, locked=0 where id='".$job."'";
            } else {
              $slice = $row["slice"];// + 1;
              $sql = "update job set slice='".($row["slice"] + 1).
                     "', locked=0 where id='".$job."'";
            }
          } else {
            $slice = $row["slice"];// + 1;
            $sql = "update job set slice='".($row["slice"] + 1).
                   "', locked=0 where id='".$job."'";
          }
        }
      } else {
        if(($row["current"] + $row["count"]) >= $row["frames"]) {
          // We have reached the last batch
          if($row["current"] == $row["frames"]) {

            // The last batch has been issued.
            // Look for the first unfinished batch (which may be the last
            // batch), and re-issue it to the client. This will have
            // the effect that several clients may be working in parallel
            // at finishing the last and any aborted batches*. The first to
            // finish wins.

	    // * If the number of clients exceed the number of unfinished
	    // or aborted batches.

            $sql2 = "select * from batch where job='".$job."'".
                    " and finished='0' limit 1";
            $result2 = mysql_query($sql2);
            if(mysql_num_rows($result2)) {
              $row2 = mysql_fetch_array($result2);

              if(file_exists($_SERVER["DOCUMENT_ROOT"].$output_dir.
                             $row["name"]."/".$row["name"]."_frame_".
                             (str_pad($row2["frame"], 8, "0", STR_PAD_LEFT)).
                             ".zip")) {
                $uploaded = 1;
              }
              if($row2["aborted"]) {
                $aborted = "";
              } else {
                $aborted = ", aborted='".time()."' ";
              }
              $sql3 = "update batch set finished='".$row2["issued"]."'".
                      $aborted." where id='".$row2["id"]."'";
              mysql_query($sql3);

              $startframe = $row2["frame"];
              $stopframe = $row2["frame"] + $row2["count"] - 1;

              $sql = "update job set locked=0 ".
                     "where id='".$job."'";
            } else {

              // All batches have been issued, and all batches have been
              // finished. Nothing more to do; mark job as finished.

// Todo:
// Check if all batches really are uploaded.
// If they are, mark job finished.
// If not, reissue those not uploaded.

              $sql = "update job set locked=0, finished='".time()."' ".
                     "where id='".$job."'";
              $skipbatch = 1;
            }

          } else {
            // there is just one batch left
            // take care not to overshoot current
            $stopframe = $row["frames"] - 1;
            $sql = "update job set current='".($row["frames"]).
                   "', locked=0 where id='".$job."'";
          }
        } else {
          // current has not yet reached frames
          // there are plenty of batches left
          // just issue one
          $stopframe = $row["current"] + ($row["count"] - 1);
          $sql = "update job set current='".($row["current"]+$row["count"]).
                 "', locked=0 where id='".$job."'";
        }
      }

      $result = mysql_query($sql);

      if(!$skipbatch) {
        if(!$uploaded) {
          $sql = "insert into batch set job='".$job."', ".
                 "frame='".$startframe."', ".
                 "count='".(1+$stopframe-$startframe)."', issued='".time()."', ".
                 "client='".$client[0]."', cid='".$client[1]."', ".
                 "cgroup='".$cgroup."'".
                 ($row["sliced"]?", slice='".$slice."'":"");
          $result = mysql_query($sql);
          if(!mysql_insert_id()) {
            echo "error=".__LINE__."\n";
          } else {
            echo "command=render\n";
            echo "batch=".mysql_insert_id()."\n";
            echo "startframe=".$startframe."\n";
            echo "stopframe=".$stopframe."\n";
            if($row["sliced"]) {
              echo "slice=".$slice."\n";
              echo "startrow=".$startrow."\n";
              echo "endrow=".$endrow."\n";
            }
          }
        } else {
          echo "command=sleep\n";
          // echo "message=batchloopsleep\n";
          log_state("sleep");
        }
      } else {
	//        echo "command=sleep\n";
        echo "command=getjob\n";
      }
    } else {
      //The job couldn't be locked. This could be due to
      //concurrent clients getting batches, a crashed server,
      //a finished job or similar things. In any case, telling
      //the client to look for a job won't hurt too much.
      echo "command=getjob\n";
    }
  break;
  case "postbatch":
    $error = 0;
    $batch = mysql_real_escape_string($_GET["batch"]);
    $sql = "select * from batch where ((finished = issued and ".
           "aborted != 0) or finished = 0) and id='".$batch."'";
    $result = mysql_query($sql);
    if(mysql_num_rows($result)) {
      if($_FILES["filedata"]) {
        $sql = "select name from job, batch where batch.job = job.id ".
               "and batch.id='".$batch."'";
        $result = mysql_query($sql);
        $row = mysql_fetch_array($result);
        $name = $row["name"];
        if(!file_exists($_SERVER["DOCUMENT_ROOT"].$output_dir.
                        $row["name"])) {
          mkdir($_SERVER["DOCUMENT_ROOT"].$output_dir.$name);
        }
        if(!file_exists($_SERVER["DOCUMENT_ROOT"].$output_dir.
                        $name."/".$_FILES["filedata"]["name"])) {
          if(!$_FILES["filedata"]["error"]) {
            move_uploaded_file($_FILES["filedata"]["tmp_name"], 
                               $_SERVER["DOCUMENT_ROOT"].$output_dir.
                               $name."/".$_FILES["filedata"]["name"]);
          }
          if($_FILES["filedata"]["error"] == 0) {
            $sql = "update batch set finished='".time()."', ".
                   "aborted='0' where id='".$batch."'";
            mysql_query($sql);
            echo "status=ok\n";
          } else {
            $error = 1;
            hpsyslog(LOG_WARNING, "postbatch filedata error == " . $_FILES["filedata"]["error"]);
          }
        } else {
          $sql = "update batch set finished=issued, ".
                 "aborted='0' where id='".$batch."'";
          mysql_query($sql);
          echo "status=ok\n";
        }
      } else {
        $error = 1;
        hpsyslog(LOG_WARNING, "postbatch filedata not set");
      }
    } else {
      $error = 1;
      hpsyslog(LOG_WARNING, "postbatch no such batch");
    }

    if($error) {
      echo "status=error\n";
      if(mysql_num_rows($result)) {
        $batch = mysql_real_escape_string($_GET["batch"]);
        $sql = "update batch set aborted='".time()."' where id='".$batch."'";
        $result = mysql_query($sql);
      }
    }
  break;
  case "abortbatch":
    if(array_key_exists("batch", $_GET) && array_key_exists("batch", $_GET)) {
      $batch = mysql_real_escape_string($_GET["batch"]);
      $client = explode(":", mysql_real_escape_string($_GET["client"]));
      echo "status=error\n";
      $sql = "update batch set aborted='".time()."' where id='".
             $batch."' and client='".$client[0]."' and ".
             "cid='".$client[1]."'";
      $result = mysql_query($sql);
    }
  break;
  case "abort":
  break;
  case "active":
    if(array_key_exists("batch", $_GET) && array_key_exists("batch", $_GET)) {
      $batch = mysql_real_escape_string($_GET["batch"]);
      $client = explode(":", mysql_real_escape_string($_GET["client"]));
      echo "status=ok\n";
      $sql = "update batch set active='".time()."' where id='".
             $batch."' and client='".$client[0]."' and ".
             "cid='".$client[1]."'";
      $result = mysql_query($sql);
    }
  break;
  default:
    echo "command=unknown\n";
  break;
}
?>
