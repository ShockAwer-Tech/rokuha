<?php require('repositories.php'); ?>
<?php require('models.php'); ?>
<?php
extract($_POST,EXTR_SKIP);
extract($_GET,EXTR_SKIP);
extract($_COOKIE,EXTR_SKIP);
$upfile_name=isset($_FILES["upfile"]["name"]) ? $_FILES["upfile"]["name"] : "";
$upfile=isset($_FILES["upfile"]["tmp_name"]) ? $_FILES["upfile"]["tmp_name"] : "";

define("LOGFILE", 'img.log');		//ログファイル名
define("TREEFILE", 'tree.log');		//ログファイル名
define("IMG_DIR", 'src/');		//画像保存ディレクトリ。futaba.phpから見て
define("THUMB_DIR",'thumb/');		//サムネイル保存ディレクトリ
define("TITLE", 'General');		//タイトル（<title>とTOP）
define("HOME",  '../');			//「ホーム」へのリンク
define("MAX_KB", '500');			//投稿容量制限 KB（phpの設定により2Mまで
define("MAX_W",  '250');			//投稿サイズ幅（これ以上はwidthを縮小
define("MAX_H",  '250');			//投稿サイズ高さ
define("PAGE_DEF", '5');			//一ページに表示する記事
define("LOG_MAX",  '500');		//ログ最大行数
define("ADMIN_PASS", 'admin_pass');	//管理者パス
define("RE_COL", '789922');               //＞が付いた時の色
define("PHP_SELF", 'rokuha.php');	//このスクリプト名
define("PHP_SELF2", 'rokuha.htm');	//入り口ファイル名
define("PHP_EXT", '.htm');		//1ページ以降の拡張子
define("RENZOKU", '5');			//連続投稿秒数
define("RENZOKU2", '10');		//画像連続投稿秒数
define("MAX_RES", '30');		//強制sageレス数
define("USE_THUMB", 1);		//サムネイルを作る する:1 しない:0
define("PROXY_CHECK", 0);		//proxyの書込みを制限する y:1 n:0
define("DISP_ID", 0);		//IDを表示する 強制:2 する:1 しない:0
define("BR_CHECK", 15);		//改行を抑制する行数 しない:0
define("IDSEED", 'idの種');		//idの種
define("RESIMG", 0);		//レスに画像を貼る:1 貼らない:0

$path = realpath("./").'/'.IMG_DIR;
$badstring = array("dummy_string","dummy_string2"); //拒絶する文字列
$badfile = array("dummy","dummy2"); //拒絶するファイルのmd5
$badip = array("addr.dummy.com","addr2.dummy.com"); //拒絶するホスト
$addinfo='';
?>

<?php
/**
 * Rendering of message form.
 *
 * @params string $dat message log.
 * @params integer $resno res number.
 * @params string $admin administrator password.
 * @return void
 */
function form(&$dat,$resno,$admin=""){
  global $addinfo; $msg=""; $hidden="";

  $maxbyte = MAX_KB * 1024;
  $no=$resno;

  if($resno){
    $msg .= "[<a href=\"".PHP_SELF2."\">Return</a>]\n";
    $msg .= "<table width='100%'><tr><th bgcolor=#e04000>\n";
    $msg .= "<font color=#FFFFFF>Posting mode: Reply</font>\n";
    $msg .= "</th></tr></table>\n";
  }
  if($admin){
    $hidden = "<input type=hidden name=admin value=\"".ADMIN_PASS."\">";
    $msg = "<h4>HTML tags are allowed.</h4>";
  }

  $dat.=$msg.'<center>
<form action="'.PHP_SELF.'" method="POST" enctype="multipart/form-data">
<input type=hidden name=mode value="regist">
'.$hidden.'
<input type=hidden name="MAX_FILE_SIZE" value="'.$maxbyte.'">
';

  if($no){
    $dat.='<input type=hidden name=resto value="'.$no.'">';
  }

  $dat.='<table cellpadding=1 cellspacing=1>
  <tr><td bgcolor=#eeaa88><b>Name</b></td><td><input type=text name=name size="28"></td></tr>
  <tr><td bgcolor=#eeaa88><b>E-mail</b></td><td><input type=text name=email size="28"></td></tr>
  <tr><td bgcolor=#eeaa88><b>Subject</b></td><td><input type=text name=sub size="35">
  <input type="submit" value="Submit"></td></tr>
  <tr><td bgcolor=#eeaa88><b>Comment</b></td><td><textarea name=com cols="48" rows="4" wrap=soft></textarea></td></tr>
  ';

  if(RESIMG || !$resno){
    $dat.='<tr><td bgcolor=#eeaa88><b>File</b></td>
    <td><input type=file name=upfile size="35">
    [<label><input type=checkbox name=textonly value=on>No File</label>]</td></tr>';
  }

  $dat.='<tr><td bgcolor=#eeaa88><b>Password</b></td><td><input type=password name=pwd size=8 maxlength=8 value=""><small>(Password used for file deletion)</small></td></tr>
  <tr><td colspan=2>
  <small>
  <ul>
  <LI>Supported file types are: GIF, JPG, PNG</LI>
  <LI>Maximum file size allowed is '.MAX_KB.' KB</LI>
  <LI>Images greater than '.MAX_W.'x'.MAX_H.' pixels will be thumbnailed.</LI>
  </ul>
  '.$addinfo.'</small></td></tr></table></form></center><hr>';
}
?>

<?php
/**
 * Update message.
 * 
 * @params integer $resno target message number.
 * @return void
 */
function updatelog($resno=0){
  global $path;$p=0;

  $tree = file(TREEFILE);
  $find = false;
  if($resno){
    $counttree=count($tree);
    for($i = 0;$i<$counttree;$i++){
      list($artno,)=explode(",",rtrim($tree[$i]));
      if($artno==$resno){ //レス先検索
        $st=$i;$find=true;break;
      }
    }
    if(!$find){
      error("Error: Thread specified does not exist.");
    }
  }
  $line = file(LOGFILE);
  $countline=count($line);
  for($i = 0; $i < $countline; $i++){
    list($no,) = explode(",", $line[$i]);
    $lineindex[$no]=$i + 1; //逆変換テーブル作成
  }

  $counttree = count($tree);
  for($page=0;$page<$counttree;$page+=PAGE_DEF){
    $dat='';
    head($dat);
    form($dat,$resno);
    if(!$resno){
      $st = $page;
    }
    $dat.='<form action="'.PHP_SELF.'" method=POST>';

  for($i = $st; $i < $st+PAGE_DEF; $i++){
    if(empty($tree[$i])){
      continue;
    }
    $treeline = explode(",", rtrim($tree[$i]));
    $disptree = $treeline[0];
    $j=$lineindex[$disptree] - 1; //該当記事を探して$jにセット
    if(empty($line[$j])){
      continue;
    } //$jが範囲外なら次の行
    
    list($no,$now,$name,$email,$sub,$com,$url,
         $host,$pwd,$ext,$w,$h,$time,$chk) = explode(",", $line[$j]);
    // URLとメールにリンク
    if($email){
      $name = "<a href=\"mailto:$email\">$name</a>";
    }
    $com = auto_link($com);
    $com = preg_replace("/(^|>)(&gt;[^<]*)/i", "\\1<font color=".RE_COL.">\\2</font>", $com);
    // 画像ファイル名
    $img = $path.$time.$ext;
    $src = IMG_DIR.$time.$ext;
    // <imgタグ作成
    $imgsrc = "";
    if($ext && is_file($img)){
      $size = filesize($img);//altにサイズ表示
      if($w && $h){//サイズがある時
        if(@is_file(THUMB_DIR.$time.'s.jpg')){
          $imgsrc = "<small>Thumbnail displayed, click image for full size.</small><br><a href=\"".$src."\" target=_blank><img src=".THUMB_DIR.$time.'s.jpg'.
      " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
        }
        else{
          $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
      " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
        }
      }
      else{//それ以外
        $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
      " border=0 align=left hspace=20 alt=\"".$size." B\"></a>";
      }
      $dat.="File: <a href=\"$src\" target=_blank>$time$ext</a>-($size B)<br>$imgsrc";
    }

    // メイン作成
    $dat.="<input type=checkbox name=\"$no\" value=delete><font color=#cc1105 size=+1><b>$sub</b></font> \n";
    $dat.="Name <font color=#117743><b>$name</b></font> $now No.$no &nbsp; \n";
    if(!$resno) $dat.="[<a href=".PHP_SELF."?res=$no>Reply</a>]";
    $dat.="\n<blockquote>$com</blockquote>";

     // そろそろ消える。
     if($lineindex[$no]-1 >= LOG_MAX*0.95){
      $dat.="<font color=\"#f00000\"><b>Since this thread is old, it will soon disappear.</b></font><br>\n";
     }

    //レス作成
    if(!$resno){
      $s=count($treeline) - 10;
      if($s<1){
        $s=1;
      }
      elseif($s>1){
       $dat.="<font color=\"#707070\">レス".
              ($s - 1)."posts omitted. Click Reply to view.</font><br>\n";
      }
    }
    else{
      $s=1;
    }

    for($k = $s; $k < count($treeline); $k++){
      $disptree = $treeline[$k];
      $j=$lineindex[$disptree] - 1;
      if($line[$j]==""){
        continue;
      }
      list($no,$now,$name,$email,$sub,$com,$url,
           $host,$pwd,$ext,$w,$h,$time,$chk) = explode(",", $line[$j]);
      // URLとメールにリンク
      if($email) $name = "<a href=\"mailto:$email\">$name</a>";
      $com = auto_link($com);
      $com = preg_replace("/(^|>)(&gt;[^<]*)/i", "\\1<font color=".RE_COL.">\\2</font>", $com);

      // 画像ファイル名
      $img = $path.$time.$ext;
      $src = IMG_DIR.$time.$ext;
      // <imgタグ作成
      $imgsrc = "";
      if($ext && is_file($img)){
        $size = filesize($img);//altにサイズ表示
        if($w && $h){//サイズがある時
          if(@is_file(THUMB_DIR.$time.'s.jpg')){
            $imgsrc = "<small>Thumbnail displayed, click image for full size.</small><br><a href=\"".$src."\" target=_blank><img src=".THUMB_DIR.$time.'s.jpg'.
        " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
          }
          else{
            $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
        " border=0 align=left width=$w height=$h hspace=20 alt=\"".$size." B\"></a>";
          }
        }
        else{//それ以外
          $imgsrc = "<a href=\"".$src."\" target=_blank><img src=".$src.
        " border=0 align=left hspace=20 alt=\"".$size." B\"></a>";
        }
        $imgsrc="<br> &nbsp; &nbsp; <a href=\"$src\" target=_blank>$time$ext</a>-($size B) $imgsrc";
      }

        // メイン作成
        $dat.="<table border=0><tr><td nowrap align=right valign=top>…</td><td bgcolor=#F0E0D6 nowrap>\n";
        $dat.="<input type=checkbox name=\"$no\" value=delete><font color=#cc1105 size=+1><b>$sub</b></font> \n";
        $dat.="Name <font color=#117743><b>$name</b></font> $now No.$no &nbsp; \n";
        $dat.="$imgsrc<blockquote>$com</blockquote>";
        $dat.="</td></tr></table>\n";
      }
      $dat.="<br clear=left><hr>\n";
      clearstatcache();//ファイルのstatをクリア
      $p++;
      if($resno){
        break;
      } //res時はtree1行だけ
    }

    $dat.='<table align=right><tr><td nowrap align=center>
<input type=hidden name=mode value=usrdel>Delete Post [<input type=checkbox name=onlyimgdel value=on>File Only]<br>
Password <input type=password name=pwd size=8 maxlength=8 value="">
<input type=submit value="Delete"></form></td></tr></table>';

    if(!$resno){ //res時は表示しない
      $prev = $st - PAGE_DEF;
      $next = $st + PAGE_DEF;
      // 改ページ処理
      $dat.="<table align=left border=1><tr>";
      if($prev >= 0){
        if($prev==0){
          $dat.="<form action=\"".PHP_SELF2."\" method=get><td>";
        }
        else{
          $dat.="<form action=\"".$prev/PAGE_DEF.PHP_EXT."\" method=get><td>";
        }
        $dat.="<input type=submit value=\"Previous\">";
        $dat.="</td></form>";
      }
      else{
        $dat.="<td>Previous</td>";
      }

      $dat.="<td>";
      for($i = 0; $i < count($tree) ; $i+=PAGE_DEF){
        if($st==$i){
          $dat.="[<b>".($i/PAGE_DEF)."</b>] ";
        }
        else{
          if($i==0){
            $dat.="[<a href=\"".PHP_SELF2."\">0</a>] ";
          }
          else{
            $dat.="[<a href=\"".($i/PAGE_DEF).PHP_EXT."\">".($i/PAGE_DEF)."</a>] ";
          }
        }
      }

      $dat.="</td>";

      if($p >= PAGE_DEF && count($tree) > $next){
        $dat.="<form action=\"".$next/PAGE_DEF.PHP_EXT."\" method=get><td>";
        $dat.="<input type=submit value=\"Next\">";
        $dat.="</td></form>";
      }
      else{
        $dat.="<td>Next</td>";
      }
        $dat.="</tr></table><br clear=all>\n";
    }
    
    foot($dat);
    if($resno){
      echo $dat;break;
    }
    if($page==0){
      $logfilename=PHP_SELF2;
    }
    else{
      $logfilename=$page/PAGE_DEF.PHP_EXT;
    }

    $fp = fopen($logfilename, "w");
    set_file_buffer($fp, 0);
    rewind($fp);
    fputs($fp, $dat);
    fclose($fp);
    chmod($logfilename,0666);
  }

  if(!$resno&&is_file(($page/PAGE_DEF+1).PHP_EXT)){
    unlink(($page/PAGE_DEF+1).PHP_EXT);
  }
}
?>

<?php
/* フッタ */
/**
 * Rendering of footer.
 *
 * @params string $dat string of log.
 * @return void
 */
function foot(&$dat){
  $dat.='
<center>
<small><!-- GazouBBS v3.0 --><!-- ふたば改0.8 -->
- <a href="http://php.s3.to" target=_top>GazouBBS</a> + <a href="http://www.2chan.net/" target=_top>futaba</a> + <a href="https://github.com/outrunning/futabawall" target=_top>futabawall</a> -
</small>
</center>
</body></html>';
}
?>

<?php
/**
 * Create http link.
 *
 * @params string $message message.
 * @return string Replaced to the link.
 */
function auto_link($message){
  return preg_replace(
    "/(https?|ftp|news)(:\/\/[[:alnum:]\+\$\;\?\.%,!#~*\/:@&=_-]+)/",
    "<a href=\"\\1\\2\" target=\"_blank\">\\1\\2</a>",
    $message
  );
}
?>

<?php
/**
 * Rendering of error page.
 *
 * @params string $mes message
 * @params string $dest upload file path.
 * @return void
 */
function error($mes,$dest=''){
  global $upfile_name,$path;

  if(is_file($dest)){
    unlink($dest);
  }

  head($dat);

  echo $dat;
  echo "<br><br><hr size=1><br><br>
        <center><font color=red size=5><b>$mes<br><br><a href=".PHP_SELF2.">Return</a></b></font></center>
        <br><br><hr size=1>";
  die("</body></html>");
}
?>

<?php
/**
 * Connect to port with reverse proxy. 
 * 
 * @params integer $port target port.
 * @return integer 1 then success.
 *         integer 0 then error.
 */
function proxy_connect($port){
  $a="";$b="";
  $fp = @fsockopen($_SERVER["REMOTE_ADDR"], $port,$a,$b,2);
  if(!$fp){
    return 0;
  }
  else{
    return 1;
  }
}
?>

<?php
//サムネイル作成
/**
 * Build of thumbnail file.
 *
 * @params string $path file path.
 * @params string $tim timestamp.
 * @params string $ext extention name.
 * @return void
 */
function thumb($path,$tim,$ext){
  if(!function_exists("ImageCreate") ||
     !function_exists("ImageCreateFromJPEG")){
    return;
  }

  $fname=$path.$tim.$ext;
  $thumb_dir = THUMB_DIR;     //サムネイル保存ディレクトリ
  $width     = MAX_W;            //出力画像幅
  $height    = MAX_H;            //出力画像高さ
  // 画像の幅と高さとタイプを取得
  $size = GetImageSize($fname);
  switch ($size[2]) {
    case 1 :
      if(function_exists("ImageCreateFromGIF")){
        $im_in = @ImageCreateFromGIF($fname);
        if($im_in){break;}
      }
      if(!is_executable(realpath("./gif2png")) || 
         !function_exists("ImageCreateFromPNG")){
        return;
      }

      @exec(realpath("./gif2png")." $fname",$a);

      if(!file_exists($path.$tim.'.png')){
        return;
      }
      $im_in = @ImageCreateFromPNG($path.$tim.'.png');
      unlink($path.$tim.'.png');
      if(!$im_in){
        return;
      }
      break;

    case 2 : 
      $im_in = @ImageCreateFromJPEG($fname);
      if(!$im_in){
        return;
      }
      break;
    case 3 :
      if(!function_exists("ImageCreateFromPNG")){
        return;
      }
      $im_in = @ImageCreateFromPNG($fname);
      if(!$im_in){
        return;
      }
      break;
    default : 
      return;
  }
  // リサイズ
  if ($size[0] > $width || $size[1] >$height) {
    $key_w = $width / $size[0];
    $key_h = $height / $size[1];
    ($key_w < $key_h) ? $keys = $key_w : $keys = $key_h;
    $out_w = ceil($size[0] * $keys) +1;
    $out_h = ceil($size[1] * $keys) +1;
  } else {
    $out_w = $size[0];
    $out_h = $size[1];
  }
  // 出力画像（サムネイル）のイメージを作成
  if(function_exists("ImageCreateTrueColor")&&get_gd_ver()=="2"){
    $im_out = ImageCreateTrueColor($out_w, $out_h);
  }
  else{
    $im_out = ImageCreate($out_w, $out_h);
  }
  // 元画像を縦横とも コピーします。
  ImageCopyResized($im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $size[0], $size[1]);
  // サムネイル画像を保存
  ImageJPEG($im_out, $thumb_dir.$tim.'s.jpg',60);
  chmod($thumb_dir.$tim.'s.jpg',0666);
  // 作成したイメージを破棄
  ImageDestroy($im_in);
  ImageDestroy($im_out);
}
?>

<?php
/**
 * Publish to futaba borad.
 *
 * @params string $name user name.
 * @params string $email user email address.
 * @params string $sub subject.
 * @params string $comment user comment.
 * @params string $url 
 * @params string $pwd user password.
 * @params string $upfile upload file path.
 * @params string $upfile_name upload filename.
 * @params string $resto thread target number.
 * @return void
 */
function regist($name,$email,$sub,$comment,$url,$pwd,$upfile,$upfile_name,$resto){
  global $path,$badstring,$badfile,$badip,$pwdc,$textonly;
  $dest="";$mes="";

  // 時間
  $time = time();
  $tim = $time.substr(microtime(),2,3);

  // アップロード処理
  if($upfile&&file_exists($upfile)){
    $dest = ImageFile::getNew()->createTempFileName($path, $tim);
    move_uploaded_file($upfile, $dest);
    //↑でエラーなら↓に変更
    //copy($upfile, $dest);
    $upfile_name = CleanStr($upfile_name);
    if(!is_file($dest)){
      error("Error: Upload failed.",$dest);
    }
    $size = getimagesize($dest);
    if(!is_array($size)){
      error("Error: Upload failed.",$dest);
    }
    $is_uploaded = ImageFile::getNew()->isUploaded($badfile, $dest);
    if ($is_uploaded === true) {
      error("Error: Duplicate md5 checksum detected.", $dest); //拒絶画像
      return;
    }
    chmod($dest,0666);
   
    // size[0] is width, size[1] is height. 
    $desired_size = ImageFile::adjustmentImageCanvasSize(
      $size[0], $size[1]
    );
    $W = $desired_size['width'];
    $H = $desired_size['height'];
    $extension = ExtensionRepository::find($size[2]);

    $mes = "$upfile_name uploaded!<br><br>";
  }

  foreach($badstring as $value){
    $pattern = '/' . $value . '/';
    if(preg_match($pattern, $comment) === 1 || 
       preg_match($pattern, $sub) === 1 || 
       preg_match($pattern, $name) === 1 || 
       preg_match($pattern, $email) === 1 ){
      error("Error: String refused.(str)",$dest);
    };
  }
  if($_SERVER["REQUEST_METHOD"] != "POST"){
    error("Error: Unjust POST.(post)",$dest);
  }
  // フォーム内容をチェック
  if(!$name||preg_match("/^[ |　|]*$/",$name) === 1){
    $name="";
  }
  if(!$comment||preg_match("/^[ |　|\t]*$/",$comment) === 1){
    $comment="";
  }
  if(!$sub||preg_match("/^[ |　|]*$/",$sub) === 1){
    $sub=""; 
  }

  if(!$resto&&!$textonly&&!is_file($dest)){
    error("Error: No file selected.",$dest);
  }
  if(!$comment&&!is_file($dest)){
    error("Error: No text entered.",$dest);
  }

  $name=preg_replace("/Manager/","\"Manager\"",$name);
  $name=preg_replace("/Deletion/","\"Deletion\"",$name);

  if(strlen($comment) > 1000){
    error("Error: Field too long.",$dest);
  }
  if(strlen($name) > 100){
    error("Error: Field too long.",$dest);
  }
  if(strlen($email) > 100){
    error("Error: Field too long.",$dest);
  }
  if(strlen($sub) > 100){
    error("Error: Field too long.",$dest);
  }
  if(strlen($resto) > 10){
    error("Error: Abnormal reply.",$dest);
  }
  if(strlen($url) > 10){
    error("Error: Abnormal reply.",$dest);
  }

  //ホスト取得
  $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);

  foreach($badip as $value){ //拒絶host
    if(preg_match("/$value$/i",$host)){
     error("Error: String refused.(host)",$dest);
    }
  }

  if(preg_match("/^mail/i",$host)
    || preg_match("/^ns/i",$host)
    || preg_match("/^dns/i",$host)
    || preg_match("/^ftp/i",$host)
    || preg_match("/^prox/i",$host)
    || preg_match("/^pc/i",$host)
    || preg_match("/^[^\.]\.[^\.]$/i",$host)){
    $pxck = "on";
  }

  if(preg_match("/ne\\.jp$/i",$host)||
    preg_match("/ad\\.jp$/i",$host)||
    preg_match("/bbtec\\.net$/i",$host)||
    preg_match("/aol\\.com$/i",$host)||
    preg_match("/uu\\.net$/i",$host)||
    preg_match("/asahi-net\\.or\\.jp$/i",$host)||
    preg_match("/rim\\.or\\.jp$/i",$host)
    ){
    $pxck = "off";
  }
  else{
    $pxck = "on";
  }

  if($pxck=="on" && PROXY_CHECK){
    if(proxy_connect('80') == 1){
      error("ＥＲＲＯＲ！　公開ＰＲＯＸＹ規制中！！(80)",$dest);
    } elseif(proxy_connect('8080') == 1){
      error("ＥＲＲＯＲ！　公開ＰＲＯＸＹ規制中！！(8080)",$dest);
    }
  }

  // No.とパスと時間とURLフォーマット
  srand((double)microtime()*1000000);
  if($pwd==""){
    if($pwdc==""){
      $pwd=rand();$pwd=substr($pwd,0,8);
    }else{
      $pwd=$pwdc;
    }
  }

  $c_pass = $pwd;
  $pass = ($pwd) ? substr(md5($pwd),2,8) : "*";
  $youbi = array('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
  $yd = $youbi[gmdate("w", $time+9*60*60)] ;
  $now = (
    gmdate("y/m/d",$time+9*60*60) . 
    "(" .(string)$yd . ")" . 
    gmdate("H:i",$time+9*60*60)
  );

  if(DISP_ID){
    if($email&&DISP_ID==1){
      $now .= " ID:???";
    }else{
      $now.=" ID:".substr(crypt(md5($_SERVER["REMOTE_ADDR"].IDSEED.gmdate("Ymd", $time+9*60*60)),'id'),-8);
    }
  }

  $email = PrettifyText::replaceStringOfMail($email);
  $sub   = PrettifyText::replaceStringOfSubject($sub);
  $url   = PrettifyText::replaceStringOfUrl($url);
  $resto = PrettifyText::replaceStringOfResNumber($resto);
  $comment = PrettifyText::replaceStringOfComment($comment);
  $name  = PrettifyText::replaceStringOfName($name);
  $names = $name;

  if(!$name){
    $name="Anonymous";
  }
  if(!$comment){
    $comment="No Text";
  }
  if(!$sub){
    $sub="No Subject"; 
  }

  //ログ読み込み
  $fp=fopen(LOGFILE,"r+");
  flock($fp, 2);
  rewind($fp);
  $buf=fread($fp,1000000);
  if($buf==''){ 
    error("error load log",$dest);
  }
  $line = explode("\n",$buf);
  $countline=count($line);
  for($i = 0; $i < $countline; $i++){
    if($line[$i]!=""){
      list($artno,)=explode(",", rtrim($line[$i]));  //逆変換テーブル作成
      $lineindex[$artno]=$i+1;
      $line[$i].="\n";
    }
  }

  // 二重投稿チェック
  $imax=count($line)>20 ? 20 : count($line)-1;
  for($i=0;$i<$imax;$i++){
    list($lastno,,$lname,,,$lcom,,$lhost,$lpwd,,,,$ltime,) = explode(",", $line[$i]);
    if(strlen($ltime)>10){
      $ltime=substr($ltime,0,-3);
    }
    if($host==$lhost||substr(md5($pwd),2,8)==$lpwd||substr(md5($pwdc),2,8)==$lpwd){
      $p=1;
    }
    else{
      $p=0;
    }

    if(RENZOKU && $p && $time - $ltime < RENZOKU){
      
    }

    if(RENZOKU && $p && $time - $ltime < RENZOKU2 && $upfile_name){
     
    }
    if(RENZOKU && $p && $comment == $lcom && !$upfile_name){
     
    }
  }

  // ログ行数オーバー
  if(count($line) >= LOG_MAX){
    for($d = count($line)-1; $d >= LOG_MAX-1; $d--){
      list($dno,,,,,,,,,$dext,,,$dtime,) = explode(",", $line[$d]);
      if(is_file($path.$dtime.$dext)){
        unlink($path.$dtime.$dext);
      }
      if(is_file(THUMB_DIR.$dtime.'s.jpg')){
        unlink(THUMB_DIR.$dtime.'s.jpg');
      }
      $line[$d] = "";
      treedel($dno);
    }
  }
  // アップロード処理
  if($dest&&file_exists($dest)){
    $imax=count($line)>200 ? 200 : count($line)-1;

    for($i=0;$i<$imax;$i++){ //画像重複チェック
      list(,,,,,,,,,$extensionp,,,$timep,$p,) = explode(",", $line[$i]);
      if($p==$is_uploaded&&file_exists($path.$timep.$extensionp)){
        error("Error: Duplicate file entry detected.",$dest);
      }
    }
  }
  list($lastno,) = explode(",", $line[0]);
  $no = $lastno + 1;
  isset($extension)?0:$extension="";
  isset($W)?0:$W="";
  isset($H)?0:$H="";
  isset($chk)?0:$chk="";
  $newline = "$no,$now,$name,$email,$sub,$comment,$url,$host,$pass,$extension,$W,$H,$tim,$,\n";
  $newline.= implode('', $line);
  ftruncate($fp,0);
  set_file_buffer($fp, 0);
  rewind($fp);
  fputs($fp, $newline);

    //ツリー更新
  $find = false;
  $newline = '';
  $tp=fopen(TREEFILE,"r+");
  set_file_buffer($tp, 0);
  rewind($tp);
  $buf=fread($tp,1000000);
  if($buf==''){error("error tree update",$dest);}
  $line = explode("\n",$buf);
  $countline=count($line);
  for($i = 0; $i < $countline; $i++){
    if($line[$i]!=""){
      $line[$i].="\n";
      $j=explode(",", rtrim($line[$i]));
      if($lineindex[$j[0]]==0){
        $line[$i]='';
      } 
    } 
  }
  if($resto){
    for($i = 0; $i < $countline; $i++){
      $rtno = explode(",", rtrim($line[$i]));
      if($rtno[0]==$resto){
        $find = TRUE;
        $line[$i]=rtrim($line[$i]).','.$no."\n";
        $j=explode(",", rtrim($line[$i]));
        if(count($j)>MAX_RES){
          $email='sage';
        }
        if(!stristr($email,'sage')){
          $newline=$line[$i];
          $line[$i]='';
        }
        break;
      } 
    } 
  }

  if(!$find){
    if(!$resto){
      $newline="$no\n";
    }
    else{
      error("Error: Thread specified does not exist.",$dest);
    }
  }
  $newline.=implode('', $line);
  ftruncate($tp,0);
  set_file_buffer($tp, 0);
  rewind($tp);
  fputs($tp, $newline);
  fclose($tp);
  fclose($fp);

  //クッキー保存
  setcookie ("pwdc", $c_pass,time()+7*24*3600);  /* 1週間で期限切れ */
  if(function_exists("mb_internal_encoding")&&function_exists("mb_convert_encoding")
      &&function_exists("mb_substr")){
    if(preg_match("/MSIE|Opera/",$_SERVER["HTTP_USER_AGENT"]) === 1){
      $i=0;$c_name='';
      mb_internal_encoding("UTF-8");
      while($j=mb_substr($names,$i,1)){
        $j = mb_convert_encoding($j, "UTF-16", "UTF-8");
        $c_name.="%u".bin2hex($j);
        $i++;
      }
      header(
        "Set-Cookie: namec=$c_name; expires=".gmdate("D, d-M-Y H:i:s",time()+7*24*3600)." GMT",false
      );
    }
    else{
      $c_name=$names;
      setcookie ("namec", $c_name,time()+7*24*3600);  /* 1週間で期限切れ */
    }
  }

  if($dest&&file_exists($dest)){
    rename($dest,$path.$tim.$extension);
    if(USE_THUMB){thumb($path,$tim,$extension);}
  }
  updatelog();

  echo "<html><head><meta charset=\"UTF-8\"><meta http-equiv=\"refresh\" content=\"1;URL=".PHP_SELF2."\"></head>";
  echo "<body>$mes Updating page.</body></html>";
}
?>

<?php
/**
 * Get GD Version.
 *
 * @return string GD Information.
 */
function get_gd_ver(){
  if(function_exists("gd_info")){
    $gdver=gd_info();
    $phpinfo=$gdver["GD Version"];
  }
  else{ //php4.3.0未満用
    ob_start();
    phpinfo(8);
    $phpinfo=ob_get_contents();
    ob_end_clean();
    $phpinfo=strip_tags($phpinfo);
    $phpinfo=stristr($phpinfo,"gd version");
    $phpinfo=stristr($phpinfo,"version");
  }

  $end=strpos($phpinfo,".");
  $phpinfo=substr($phpinfo,0,$end);
  $length = strlen($phpinfo)-1;
  $phpinfo=substr($phpinfo,$length);
  return $phpinfo;
}
?>

<?php
/**
 * Seek MD5 checksum of file.
 *
 * @params string $inFile file path.
 * @return string MD5 checksum.
 *         false Is error.
 */
function md5_of_file($in_file) {
 if (file_exists($in_file)){
   if(function_exists('md5_file')){
     return md5_file($in_file);
   }else{
     $fd = fopen($in_file, 'r');
     $fileContents = fread($fd, filesize($in_file));
     fclose ($fd);
     return md5($fileContents);
   }
 }else{
   return false;
 }
}
?>

<?php
/**
 * Delete of message.
 *
 * @params integer $delno delete message number.
 * @return void
 */
function treedel($delno){
  $fp=fopen(TREEFILE,"r+");
  set_file_buffer($fp, 0);
  flock($fp, 2);
  rewind($fp);
  $buf=fread($fp,1000000);
  if($buf==''){
    error("error tree del");
  }
  $line = explode("\n",$buf);
  $countline=count($line);
  if($countline>2){
    for($i = 0; $i < $countline; $i++){
      if($line[$i]!=""){
        $line[$i].="\n";
      }
    }
    for($i = 0; $i < $countline; $i++){
      $treeline = explode(",", rtrim($line[$i]));
      $counttreeline=count($treeline);
      for($j = 0; $j < $counttreeline; $j++){
        if($treeline[$j] == $delno){
          $treeline[$j]='';
          if($j==0){
            $line[$i]='';
          }
          else{
            $line[$i]=implode(',', $treeline);
            $line[$i]=preg_replace("/,,/",",",$line[$i]);
            $line[$i]=preg_replace("/,$/","",$line[$i]);
            $line[$i].="\n";
          }
          break 2;
        } 
      } 
    }
    ftruncate($fp,0);
    set_file_buffer($fp, 0);
    rewind($fp);
    fputs($fp, implode('', $line));
  }
  fclose($fp);
}
?>

<?php
/** 
 * Delete of user post message.
 *
 * @params integer $no post message number.
 * @params string $pwd post message password.
 * @return void
 */
function usrdel($no,$pwd){
  global $path,$pwdc,$onlyimgdel;
  $host = gethostbyaddr($_SERVER["REMOTE_ADDR"]);
  $delno = array("dummy");
  $delflag = false;
  reset($_POST);
  while ($item = each($_POST)){
    if($item[1]=='delete'){
      array_push($delno,$item[0]);
      $delflag=true;
    }
  }

  if($pwd==""&&$pwdc!=""){
    $pwd=$pwdc;
  }

  $fp=fopen(LOGFILE,"r+");
  set_file_buffer($fp, 0);
  flock($fp, 2);
  rewind($fp);
  $buf=fread($fp,1000000);
  fclose($fp);

  if($buf==''){
    error("error user del");
  }

  $line = explode("\n",$buf);
  $countline=count($line);

  for($i = 0; $i < $countline; $i++){
    if($line[$i]!=""){
      $line[$i].="\n";
    };
  }

  $flag = false;
  $countline=count($line)-1;
  for($i = 0; $i<$countline; $i++){
    list($dno,,,,,,,$dhost,$pass,$dext,,,$dtim,) = explode(",", $line[$i]);
    if(array_search($dno,$delno) && (substr(md5($pwd),2,8) == $pass || $dhost == $host||ADMIN_PASS==$pwd)){
      $flag = true;
      $line[$i] = "";			//パスワードがマッチした行は空に
      $delfile = $path.$dtim.$dext;	//削除ファイル
      if(!$onlyimgdel){
        treedel($dno);
      }
      if(is_file($delfile)){
        unlink($delfile);//削除
      }
      if(is_file(THUMB_DIR.$dtim.'s.jpg')){
        unlink(THUMB_DIR.$dtim.'s.jpg');//削除
      }
    }
  }
  if(!$flag){
    error("Error: Password incorrect.");
  }
}
?>

<?php
/**
 * Validatio of password. 
 * ...And rendering form.
 *
 * @params string $pass password.
 * @return void
 */
function valid($pass){
  if($pass && $pass != ADMIN_PASS){
    error("Error: Management password incorrect.");
  }

  head($dat);
  echo $dat;
  echo "[<a href=\"".PHP_SELF2."\">Return</a>]\n";
  echo "[<a href=\"".PHP_SELF."\">Update</a>]\n";
  echo "<table width='100%'><tr><th bgcolor=#E08000>\n";
  echo "<font color=#FFFFFF>Manager Mode</font>\n";
  echo "</th></tr></table>\n";
  echo "<p><form action=\"".PHP_SELF."\" method=POST>\n";

  // ログインフォーム
  if(!$pass){
    echo "<center><input type=radio name=admin value=del checked>Password";
    echo "<input type=radio name=admin value=post>File Only<p>";
    echo "<input type=hidden name=mode value=admin>\n";
    echo "<input type=password name=pass size=8>";
    echo "<input type=submit value=\" Submit \"></form></center>\n";
    die("</body></html>");
  }
}
?>

<?php
/**
 * Administration of message log.
 *
 * @params string $pass administration password.
 * @return void
 */
function admindel($pass){
  global $path,$onlyimgdel;

  $all=0;
  $msg="";
  $delno = array("dummy");
  $delflag = false;
  reset($_POST);

  while ($item = each($_POST)){
    if($item[1] == 'delete'){
      array_push($delno,$item[0]);
      $delflag=true;
    }
  }

  if($delflag){
    $fp = fopen(LOGFILE,"r+");
    set_file_buffer($fp, 0);
    flock($fp, 2);
    rewind($fp);
    $buf = fread($fp,1000000);
 
    if($buf==''){
      error("error admin del");
    }

    $line = explode("\n",$buf);
    $countline=count($line)-1;
  
    for($i = 0; $i < $countline; $i++){
      if($line[$i]!=""){
        $line[$i].="\n";
      }
    }

    $find = false;

    for($i = 0; $i < $countline; $i++){
      list($no,$now,$name,$email,$sub,$com,$url,$host,$pw,$ext,$w,$h,$tim,$chk) = explode(",",$line[$i]);
      if($onlyimgdel=="on"){
        if(array_search($no,$delno)){//画像だけ削除
          $delfile = $path.$tim.$ext;	//削除ファイル
          if(is_file($delfile)) unlink($delfile);//削除
          if(is_file(THUMB_DIR.$tim.'s.jpg')) unlink(THUMB_DIR.$tim.'s.jpg');//削除
        }
      }
      else{
        if(array_search($no,$delno)){//削除の時は空に
          $find = true;
          $line[$i] = "";
          $delfile = $path.$tim.$ext;	//削除ファイル
          if(is_file($delfile)){
            unlink($delfile);//削除
          }
          if(is_file(THUMB_DIR.$tim.'s.jpg')){
            unlink(THUMB_DIR.$tim.'s.jpg');//削除
          }
          treedel($no);
        }
      }
    }

    if($find){//ログ更新
      ftruncate($fp,0);
      set_file_buffer($fp, 0);
      rewind($fp);
      fputs($fp, implode('', $line));
    }
    fclose($fp);
  }

  // 削除画面を表示
  echo "<input type=hidden name=mode value=admin>\n";
  echo "<input type=hidden name=admin value=del>\n";
  echo "<input type=hidden name=pass value=\"$pass\">\n";
  echo "<center><P>Please check the checkbox of the article you want to delete and press the delete button.\n";
  echo "<p><input type=submit value=\"Delete\"> ";
  echo "<input type=reset value=\"Reset\"> ";
  echo "[<input type=checkbox name=onlyimgdel value=on>File Only]";
  echo "<P><table border=1 cellspacing=0>\n";
  echo "<tr bgcolor=6080f6><th>Delete?</th><th>Post No.</th><th>Time</th><th>Subject</th>";
  echo "<th>Name</th><th>Comment</th><th>Host</th><th>Size<br>(Bytes)</th><th>md5</th>";
  echo "</tr>\n";
  $line = file(LOGFILE);

  for($j = 0; $j < count($line); $j++){
    $img_flag = false;
    list($no,$now,$name,$email,$sub,$com,$url,
         $host,$pw,$ext,$w,$h,$time,$chk) = explode(",",$line[$j]);
    // フォーマット
    $now=preg_replace('/.{2}\/(.*)$/','\1',$now);
    $now=preg_replace('/\(.*\)/',' ',$now);

    if(strlen($name) > 10){
      $name = substr($name,0,9).".";
    }
    if(strlen($sub) > 10){
      $sub = substr($sub,0,9).".";
    }
    if($email){ 
      $name="<a href=\"mailto:$email\">$name</a>";
    }

    $com = str_replace("<br />"," ",$com);
    $com = htmlspecialchars($com);

    if(strlen($com) > 20){
      $com = substr($com,0,18) . ".";
    }

    // 画像があるときはリンク
    if($ext && is_file($path.$time.$ext)){
      $img_flag = true;
      $clip = "<a href=\"".IMG_DIR.$time.$ext."\" target=_blank>".$time.$ext."</a><br>";
      $size = filesize($path.$time.$ext);
      $all += $size;			//合計計算
      $chk= substr($chk,0,10);
    }else{
      $clip = "";
      $size = 0;
      $chk= "";
    }
    $bg = ($j % 2) ? "d6d6f6" : "f6f6f6";//背景色

    echo "<tr bgcolor=$bg><th><input type=checkbox name=\"$no\" value=delete></th>";
    echo "<th>$no</th><td><small>$now</small></td><td>$sub</td>";
    echo "<td><b>$name</b></td><td><small>$com</small></td>";
    echo "<td>$host</td><td align=center>$clip($size)</td><td>$chk</td>\n";
    echo "</tr>\n";
  }

  echo "</table><p><input type=submit value=\"Delete$msg\"> ";
  echo "<input type=reset value=\"Reset\"></form>";

  $all = (int)($all / 1024);
  echo "[ Space used: <b>$all</b> KB ]";
  die("</center></body></html>");
}
?>

<?php
/**
 * Rendering of header. 
 * 
 * @params string $dat message log.
 * @return void
 */
function head(&$dat){
  $dat.='<html><head>
<meta charset="UTF-8"/>
<!-- meta HTTP-EQUIV="pragma" CONTENT="no-cache" -->
<STYLE TYPE="text/css">
<!--
body,tr,td,th { font-size:12pt }
a:hover { color:#DD0000; }
span { font-size:20pt }
small { font-size:10pt }
-->
</STYLE>
<title>'.TITLE.'</title>
<script language="JavaScript"><!--
function l(e){var P=getCookie("pwdc"),N=getCookie("namec"),i;with(document){for(i=0;i<forms.length;i++){if(forms[i].pwd)with(forms[i]){pwd.value=P;}if(forms[i].name)with(forms[i]){name.value=N;}}}};onload=l;function getCookie(key, tmp1, tmp2, xx1, xx2, xx3) {tmp1 = " " + document.cookie + ";";xx1 = xx2 = 0;len = tmp1.length;	while (xx1 < len) {xx2 = tmp1.indexOf(";", xx1);tmp2 = tmp1.substring(xx1 + 1, xx2);xx3 = tmp2.indexOf("=");if (tmp2.substring(0, xx3) == key) {return(unescape(tmp2.substring(xx3 + 1, xx2 - xx1 - 1)));}xx1 = xx2 + 1;}return("");}
//--></script>
</head>
<body bgcolor="#FFFFEE" text="#800000" link="#0000EE" vlink="#0000EE">
<p align=right>
[<a href="'.HOME.'" target="_top">Home</a>]
[<a href="'.PHP_SELF.'?mode=admin">Manage</a>]
<p align=center>
<font color="#800000" size=5>
<b><SPAN>'.TITLE.'</SPAN></b></font>
<hr width="90%" size=1>
';
}
?>

<?php
/**
 * prettify of message.
 * 
 * @params string $message message.
 * @return string Finished special charactor the replacement.
 */
function CleanStr($message){
  global $admin;
  $trimed_message = trim($message);//先頭と末尾の空白除去
  if (get_magic_quotes_gpc()) {//¥を削除
    $strip_slashed_message = stripslashes($trimed_message);
  }
  else{
    $strip_slashed_message = $trimed_message;
  }

  if($admin != ADMIN_PASS){//管理者はタグ可能
    $trimed_tag_message = htmlspecialchars($strip_slashed_message);//タグっ禁止
    $replace_ampersand_message = str_replace("&amp;", "&", $trimed_tag_message);//特殊文字
    $replace_comma_message = str_replace(",", "&#44;", $replace_ampersand_message);//カンマを変換
    return $replace_ampersand_message;
  }
  else{
    return str_replace(",", "&#44;", $strip_slashed_message);//カンマを変換
  }
}
?>

<?php
/**
 * Bootstrap setting.
 *
 * @return void
 */
function init(){
  $err="";
  $chkfile=array(LOGFILE,TREEFILE);
  if(!is_writable(realpath("./"))){
    error("Error: Cannot write to directory.<br>");
  }

  foreach($chkfile as $value){
    if(!file_exists(realpath($value))){
      $fp = fopen($value, "w");
      set_file_buffer($fp, 0);
      if($value==LOGFILE){
        fputs($fp,"1,2002/01/01(Sun) 00:00,Anonymous,,No Subject,No Text,,,,,,,,\n");
      }
      if($value==TREEFILE){
        fputs($fp,"1\n");
      }
      fclose($fp);
      if(file_exists(realpath($value))){
        @chmod($value,0666);
      }
    }
    if(!is_writable(realpath($value))){
      $err.=$value."Error: Cannot write to directory.<br>";
    }
    if(!is_readable(realpath($value))){
      $err.=$value."Error: Cannot read from directory.<br>";
    }
  }
  @mkdir(IMG_DIR,0777);
  @chmod(IMG_DIR,0777);
  if(!is_dir(realpath(IMG_DIR))){
    $err.=IMG_DIR."Error: Directory does not exist.<br>";
  }
  if(!is_writable(realpath(IMG_DIR))){
    $err.=IMG_DIR."Error: Cannot write to directory.<br>";
  }
  if(!is_readable(realpath(IMG_DIR))){
    $err.=IMG_DIR."Error: Cannot read from directory.<br>";
  }
  if(USE_THUMB){
    @mkdir(THUMB_DIR,0777);
    @chmod(THUMB_DIR,0777);
    if(!is_dir(realpath(IMG_DIR))){
      $err.=THUMB_DIR."Error: Directory does not exist.<br>";
    }
    if(!is_writable(realpath(THUMB_DIR))){
      $err.=THUMB_DIR."Error: Cannot write to directory.<br>";
    }
    if(!is_readable(realpath(THUMB_DIR))){
      $err.=THUMB_DIR."Error: Cannot read from directory.<br>";
    }
  }
  if($err){
    error($err);
  }
}
?>

<?php
init();		//←■■初期設定後は不要なので削除可■■
$iniv=array('mode','name','email','sub','com','pwd','upfile','upfile_name','resto','pass','res','post','no');
foreach($iniv as $iniva){
  if(!isset($$iniva)){
    $$iniva="";
  }
}

switch($mode){
  case 'regist':
    regist($name,$email,$sub,$com,'',$pwd,$upfile,$upfile_name,$resto);
    break;
  case 'admin':
    valid($pass);
    if($admin=="del"){
      admindel($pass);
    }
    if($admin=="post"){
      echo "</form>";
      form($post,$res,1);
      echo $post;
      die("</body></html>");
    }
    break;
  case 'usrdel':
    usrdel($no,$pwd);
  default:
    if($res){
      updatelog($res);
    }else{
      updatelog();
      echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=".PHP_SELF2."\">";
    }
}
?>
