<?php
/**
 * This file is part of pdfReport plugin
 * @see sendPdfReport <http://extensions.sondages.pro/sendpdfreport>
 * @version 1.0.1
 **/
class pdfReportHelper extends pdf
{
  var $htmlHeader;

  private $_config = array();
  private $_aSurveyInfo = array();
  public $sLogoFile;
  public $sImageBlank;
  public $sAbsoluteUrl;
  public $sAbsolutePath='';

  function __construct() {
      parent::__construct();
  }

  public function addTitle($sTitle, $sSubtitle = '')
  {
    // Used in LS pdf, no update here
  }

  function addHeader($aPdfLanguageSettings, $sTitle, $sSubHeader)
  {
    if($this->sLogoFile)
    {
      $this->SetHeaderData($this->sLogoFile, 30, $sTitle, $sSubHeader);
      $this->SetHeaderFont(Array($aPdfLanguageSettings['pdffont'], '', 25));
      $this->SetFooterFont(Array($aPdfLanguageSettings['pdffont'], '', 10));
    }
    else
    {
      $this->SetHeaderData('', 0, $sTitle, $sSubHeader);
      $this->SetHeaderFont(Array($aPdfLanguageSettings['pdffont'], '', 25));
      $this->SetFooterFont(Array($aPdfLanguageSettings['pdffont'], '', 10));
    }
  }
  function initAnswerPDF($aSurveyInfo, $aPdfLanguageSettings, $sTitle, $sSubHeader, $sDefaultHeaderString = '')
  {

    $this->SetAuthor($aSurveyInfo['adminname']);
    $this->SetTitle($sTitle);
    $this->SetSubject($sTitle);

    $this->SetFont($aPdfLanguageSettings['pdffont']);
    $this->setLanguageArray($aPdfLanguageSettings['lg']);

    $this->addHeader($aPdfLanguageSettings, $sTitle, $sSubHeader);
    $this->AddPage();
    $this->SetFillColor(220, 220, 220);
  }

  public function Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
  {
    Yii::log("Image ".$file." tested",'info','application.plugins.sendPdfReport.pdfReportHelper.Image');
    /* Specific system of pdf : didn't touch */
    if ($file[0] === '@' || $file[0] === '*') {
      return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
    }
    /* data:image : didn't touch */
    if(strpos($file,"data:image")===0){
      return parent::Image("@".$file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
    }
    /* File in server : 3 part : direct, in DOCUMENT_ROOT (if set) absolutePath from Yii */
    if (@file_exists($file)) {
      return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
    }
    if($file[0] === '/') {
      $docRoot=isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : "";
      if (@file_exists($docRoot."/".$file)) {
        return parent::Image($docRoot."/".$file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
      }
    }
    if (@file_exists($this->sAbsolutePath."/".$file)) {
      return parent::Image($this->sAbsolutePath."/".$file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
    }
    /* Same server but didn't find with previous (can be deleted or DOCUMENT_ROOT is broken, or using alias etc … */
    if($file[0] === '/') {
      $file=$this->sAbsoluteUrl.$file;
    }
    /* Test loading image and image have width and height (else broke pdf) */
    $imageInfo=$this->getImageInfo($file);
    if($imageInfo['size'][0] && $imageInfo['size'][1]) {
      return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, $alt, $altimgs);
    }
    Yii::log("Image ".$file." not found, header return {$imageInfo['code']} , with size {$imageInfo['size'][0]}/{$imageInfo['size'][1]}: , replaced by a white image",'warning','application.plugins.sendPdfReport.pdfReportHelper.Image');
    return parent::Image($this->sImageBlank, $x, $y, 1, 1, '', $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, true, true);
  }

  /**
   * Get the header code
   * @param $url to be tested
   */
  private function getImageInfo($url)
  {
    $aImageInfo=array(
      'code'=>null,
      'size'=>array(null,null), /* no size by default */
    );
    /* preferred method : curl */
    if (( extension_loaded ("curl") )){
      $curl = curl_init();
      curl_setopt_array( $curl,
        array(
          CURLOPT_CONNECTTIMEOUT=>1, /* 1 second is already long */
          CURLOPT_TIMEOUT => 3, /* 3 seconds is already long */
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_URL => $url,
        )
      );
      curl_exec($curl);
      $aImageInfo['code'] = @curl_getinfo($curl, CURLINFO_HTTP_CODE );
      if(curl_errno($curl)) {
          Yii::log("Image ".$url." curl error ".curl_error($curl),'warning','application.plugins.sendPdfReport.pdfReportHelper.getImageInfo');
      }
      curl_close($curl);
    }else{
      $headers=@get_headers($url);
      if($header){
        $aImageInfo['code'] = substr($headers[0], 9, 3);
      }
    }
    if($aImageInfo['code'] == 200){/* curl can return error with 200 valid code (ipv6 vs ipv4 ?) */
      $aImageInfo['size']=@getimagesize($url);
      if(!$aImageInfo['size']){
        $aImageInfo['size']=array(null,null);
      }
    }
    return $aImageInfo;
  }

}
