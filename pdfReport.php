<?php

class pdfReport extends pdf 
{
    var $htmlHeader;

    private $_config = array();
    private $_aSurveyInfo = array();
    public $sLogoFile;

    function __construct() {
        parent::__construct();
    }

    //~ function addHeader($aPdfLanguageSettings, $sSiteName, $sDefaultHeaderString,$sLogoFileName="")
    //~ {
      //~ $this->setHtmlHeader($sDefaultHeaderString);
    //~ }


    //~ public function setHtmlHeader($htmlHeader) {
        //~ $this->htmlHeader = $htmlHeader;
    //~ }

    //~ public function Header() {
        //~ $this->writeHTMLCell(
            //~ $w = 0, $h = 0, $x = '', $y = '',
            //~ $this->htmlHeader, $border = array('B' => array('width' => 1, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0))), $ln = 1, $fill = 0,
            //~ $reseth = true, $align = 'C', $autopadding = false);
        //~ //$this->writeHTML($this->htmlHeader);
    //~ }
    public function addTitle($sTitle, $sSubtitle = '') 
    {
        
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
  /**
  * TODO : fix if image are not found
  */
    //~ public function Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
    //~ {
        //~ 
        //~ if($file[0]=="/" && !is_file($file) && !is_file(Yii::app()->getConfig("homedir").$file) )// Working only on linux
        //~ {
            //~ tracevar(K_PATH_IMAGES.$file);
//~ 
            //~ $file=dirname(__FILE__)."/blank.png";
        //~ }
        //~ return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, $fitonpage, $alt, $altimgs);
    //~ }
}
