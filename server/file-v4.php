<?php
header ("Access-Control-Allow-Origin: *");
?>

<html>
<body>

<?php
$data   = "";
$param  = "";
$file   = "";
$mode   = "";
$echo   = "";
$header = "";

if (isset($_GET['data' ])) $data  = $_GET['data'];
if (isset($_GET['param'])) $param = $_GET['param'];
if (isset($_GET['file' ])) $file  = $_GET['file'];
if (isset($_GET['mode' ])) $mode  = $_GET['mode'];
if (isset($_GET['echo' ])) $echo  = $_GET['echo'];

function check_crlf ($str) {
  if ($str == "") return 0;
  $cr_check = stristr ($str, "\r");
  $lf_check = stristr ($str, "\n");
  if (!(($cr_check === False) && ($lf_check === False)))
    exit ('CRLF injection detected.');
}

// txt = '{ "name": "Luke Skywalker" }';
// getparam (txt, "name") return "Luke Skywalker"
function getparam (&$txt, $pname, $def="") {
  $val = false;
  $pname = '"'.$pname.'":';
  $pos = strpos ($txt, $pname);
  if ($pos !== false) {
    $pos = $pos + strlen ($pname);
    $sp2 = strpos ($txt, ",", $pos);
    $sp3 = strpos ($txt, "}", $pos);
    if ($sp2 === false) $sp2 = $sp3;
    if ($sp3 === false) $sp3 = $sp2;
    if (($sp2 !== false) and ($sp3 !== false))
      $sp2 = min ($sp2, $sp3);
    if ($sp2 !== false)
      $val = substr ($txt, $pos, $sp2 - $pos);
  }
  if ($val === false)
    $val = $def;
  else {
    $val = trim ($val);
    $val = trim ($val, '"');
  }
  return $val;
}

check_crlf ($data);
check_crlf ($file);
check_crlf ($mode);
check_crlf ($echo);
check_crlf ($param);

$fmt  = '$';  // caractère de formatage
$fmtu = urlencode ($fmt);  // %24 = "$"

if ($echo == "") $echo = "1";

if ($mode == "") {
  $file = basename (__FILE__);
  echo "<pre>Program for recording data in text format (.TXT et .CSV).
 URL Command :
   $file?[echo=[0|1*]&amp;]mode=[a|w|d|l|t|z]&amp;file=[file_name][.txt|.csv]&amp;data=[[".$fmt."C;]10;20;...[".$fmt."N]]|json&amp;param=[name1;name2;...]

 Functions:
 - Display of operations :
     echo = [0|1*]
     $file?echo=0&amp;...

 - Add data :
     mode = Add
     $file?mode=a&amp;file=temp.txt&amp;data=".$fmt."C;10;20".$fmt."N

 - Write data :
     ** Warning, the file is reset **
     mode = Write
     $file?mode=w&amp;file=temp.txt&amp;data=".$fmt."C;10;20".$fmt."N

 - Delete the file :
     mode = Delete
     $file?mode=d&amp;file=data.txt

 - List files :
     mode = List
     $file?mode=l&amp;file=.txt

 - Displays the local date and time :
     Local time : ISO format 8601 = 2019-04-14T18:09:35+02:00
     $file?mode=t
     GMT time   : ISO format 8601 = 2019-04-14T16:09:35Z
     $file?mode=z

 - CSV data :
     data = ".$fmt."C;10;20".$fmt."N
     ".$fmtu."D = ".$fmt."D = date ('Y-m-d') = 2019-04-14
     ".$fmtu."T = ".$fmt."T = date ('H:i:s') = 18:09:35
     ".$fmtu."C = ".$fmt."C = date ('c')     = 2019-04-14T18:09:35+02:00
     ".$fmtu."Z = ".$fmt."Z = GMT time       = 2019-04-14T16:09:35Z
     ".$fmtu."N = ".$fmt."N = \\r\\n           = CrLf = \\0x13\\0x10
     %20  = ESPACE

 - JSON data :
     $file?mode=a&amp;file=temp.txt&amp;data=json&amp;param=dev_id;time;payload_raw
     List of parameter names to extract from the data block.
     The parameter \"payload_raw\" is decoded in base64.
     If the parameter list is empty, the entire JSON block is saved.

 - Security :
     To avoid any intrusion into the program,
     only text file formats (.TXT or .CSV) are allowed.
     </pre>";
} else {
  if (($file != "") && ($data != "") && (($mode == "w") || ($mode == "a"))) {
    $ext = substr (strtoupper ($file), -4);
    if (($ext != ".TXT") && ($ext != ".CSV")) {
      if ($echo === "1") echo "Error: \".TXT\" and \".CSV\" formats only.\n";
    } else {

      if ($data == "json") {
        $header = file_get_contents ('php://input');
        if ($param != "") {
          $data = "";
          if (($mode == "w") or (file_exists ($file) == False))
            $data = $param . "\r\n";

          $names = explode (";", $param);
          foreach ($names as $name) {
            $value = getparam ($header, $name, " ");
            if ($name == "payload_raw")
              $value = '"' . base64_decode ($value) . '"';
            $data = $data . $value . ";";
          }
          $data = $data . "\r\n";
        } else {
          $data = $header;
          $data = str_replace ("\r\n\r\n", " ", $data);
          $data = str_replace ("\r\n"    , " ", $data);
          $data = str_replace ("\r"      , " ", $data);
          $data = str_replace ("\n"      , " ", $data);
          $data = $data . "\r\n";
        }
      } else {

        //$data = urldecode ($data);
        $date_ = date ("Y-m-d");  // 2019-04-14
        $time_ = date ("H:i:s");  // 18:09:35
        $gmt_  = substr_replace (date ("O"), ":", -2, 0);  // format ISO 8601 : +0200 ==> +02:00
        $timez = gmdate ("Y-m-d")."T".gmdate ("H:i:s")."Z";
        $data = str_replace ($fmt."D", $date_                 , $data);
        $data = str_replace ($fmt."T", $time_                 , $data);
        $data = str_replace ($fmt."C", $date_."T".$time_.$gmt_, $data);
        $data = str_replace ($fmt."Z", $timez                 , $data);
        $data = str_replace ($fmt."N", "\r\n"                 , $data);
      }
      $fh = fopen ($file, $mode) or exit ("can't open file");
      fwrite ($fh, $data);
      fclose ($fh);
      if ($echo === "1") {
        echo "<pre>".$data."</pre>\n";
        echo $file." : ".filesize ($file)." bytes\n";
      }
    }
  }

  if ($mode == "d") {
    if (file_exists ($file) == False) {
      if ($echo === "1") echo "Error: the file \"$file\" does not exist.\n";
    } else {
      $ext = substr (strtoupper ($file), -4);
      if (($ext != ".TXT") && ($ext != ".CSV")) {
        if ($echo === "1") echo "Error: \".TXT\" and \".CSV\" formats only.\n";
      } else {
        unlink ($file);
        if ($echo === "1") echo "\"$file\" file delete.\n";
      }
    }
  }

  if ($mode == "t") {
    $date_ = date ("Y-m-d");  // 2019-04-14
    $time_ = date ("H:i:s");  // 18:09:35
    $gmt_  = substr_replace (date ("O"), ":", -2, 0);  // format ISO 8601 : +0200 ==> +02:00
    echo $date_."T".$time_.$gmt_;
  }

  if ($mode == "z") {
    $date_ = gmdate ("Y-m-d");  // 2019-04-14
    $time_ = gmdate ("H:i:s");  // 16:09:35
    echo $date_."T".$time_."Z";
  }

  if ($mode == "l") {
    $dir = ".";
    if ($handle = opendir ($dir)) {
      $count = 0;
      while (false !== ($file_ = readdir ($handle))) {
        if (($file_ != ".") && ($file_ != "..")) {
          $lenOfFileName = strlen ($file_);
          $extOffsetPos = $lenOfFileName - 5;
          if ($extOffsetPos > 0) {
            if (strpos ($file_, $file, $extOffsetPos)) {
              $count++;
              //echo "$file<br>";
              print ($count.". <a href=\"".$file_."\">".$file_."</a> (".filesize ($file_)." bytes)<br>\n");
            }
          }
        }
      }
      closedir ($handle);
    }
  }
}
?>

</body>
</html>
