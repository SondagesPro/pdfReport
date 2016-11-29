<?php
/**
 * This file is part of sendPdfReport plugin
 * @see sendPdfReport <http://extensions.sondages.pro/sendpdfreport>
 **/
class pdfReport extends pdf
{
  var $htmlHeader;

  private $_config = array();
  private $_aSurveyInfo = array();
  public $sLogoFile;
  public $sImageBlank;
  public $sAbsoluteUrl;

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
    if ($file[0] === '@' || $file[0] === '*')
    {
      return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
    }
    if (@file_exists($file))
    {
      return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, true, $alt, $altimgs);
    }
    if($file[0] === '/')
    {
      $file=$this->sAbsoluteUrl.$file;
    }
    $headers=@get_headers($file);
    if(isset($headers[0]) && $headers[0] == 'HTTP/1.1 200 OK')
    {
      return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, $alt, $altimgs);
    }
    Yii::log("Image ".$file." not found, replaced by a white image",'warning','application.plugins.sendPdfReport');
    return parent::Image($this->sImageBlank, $x, $y, 1, 1, '', $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, true, true);
  }
}
