<?php
/**
 * pdfReport Plugin for LimeSurvey
 * Use question text to create a report and send it by email.
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015-2017 Denis Chenu <http://sondages.pro>
 * @copyright 2015 Ingeus <http://www.ingeus.fr/>
 * @license AGPL v3
 * @version 0.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class pdfReport extends \ls\pluginmanager\PluginBase {
    protected $storage = 'DbStorage';
    static protected $description = 'Do a PDF report for question.';
    static protected $name = 'pdfReport';

    private $iSurveyId;
    private $iResponseId;
    private $sLanguage;

    public function init()
    {

    }
    protected $settings = array(
        'basesavedirectory'=>array(
            'type'=>'string',
            'label'=>'Directory on the server to move the file after send (set to empty to remove the file)',
            'help'=>'You can use {SID} for survey id. Plugin didn`t create directory.',
            'default'=>'',
        ),

    );

    /**
     * Get a pdf file from a string
     * @param integer $iQid
     * @return string : URI for pdf file
     */
    private function _getPdfFile($iQid)
    {
        $oQuestion=Question::model()->findByPk(array('qid'=>$iQid,'language'=>$this->sLanguage);
        if(!$oQuestion){
            Yii::log("Question number {$iQid} invalid",'error','application.plugins.sendPdfReport');
            return null;
        }
        $aReData=array(
            'saved_id'=>$this->iResponseId,
            'thissurvey'=>getSurveyInfo($this->iSurveyId,$this->sLanguage),
        );
        $sHeader=>$oQuestion->question;
        $sSubHeader=>$oQuestion->help;

        $sHeader=templatereplace($sHeader,array(),$aReData,'',false,null,array(),true);
        $sSubHeader=templatereplace($sSubHeader,array(),$aReData,'',false,null,array(),true);

        //~ return;
        $sCssContent=file_get_contents(dirname(__FILE__).'/base.css');
        $sHeader=strip_tags($sHeader);
        $sSubHeader=strip_tags($sSubHeader);

        $aSurvey=getSurveyInfo($this->iSurveyId,$this->sLanguage);
        $sSurveyName = $aSurvey['surveyls_title'];
        Yii::setPathOfAlias('sendPdfReport', dirname(__FILE__));
        //define('K_PATH_IMAGES', Yii::app()->getConfig("homedir").DIRECTORY_SEPARATOR);

        Yii::import('application.libraries.admin.pdf', true);
        Yii::import('application.helpers.pdfHelper');
        Yii::import("sendPdfReport.helpers.pdfReportHelper");

        $aPdfLanguageSettings=pdfHelper::getPdfLanguageSettings($this->sLanguage);

        $oPDF = new pdfReport();
        $oPDF->sImageBlank = realpath(dirname(__FILE__))."/blank.png";
        $oPDF->sAbsoluteUrl= App()->request->getHostInfo();
        $pdfSpecific=array('<br pagebreak="true" />','<br pagebreak="true"/>','<br pagebreak="true">','<page>','</page>');
        $pdfReplaced=array('<span>br pagebreak="true"</span>','<span>br pagebreak="true"</span>','<span>br pagebreak="true"</span>','<span>page</span>','<span>/page</span>');
        $sText=str_replace($pdfSpecific, $pdfReplaced, $sText);
        if(function_exists ("tidy_parse_string")) // Call to undefined function tidy_parse_string() in ./application/third_party/tcpdf/include/tcpdf_static.php on line 2099
        {
            $tidy_options = array (
                'clean' => 1,
                'drop-empty-paras' => 0,
                'drop-proprietary-attributes' => 0,
                'fix-backslash' => 1,
                'hide-comments' => 1,
                'join-styles' => 1,
                'lower-literals' => 1,
                'merge-divs' => 1,
                'merge-spans' => 1,
                'output-xhtml' => 1,
                'word-2000' => 0,
                'wrap' => 0,
                'output-bom' => 0,
                'char-encoding' => 'utf8',
                'input-encoding' => 'utf8',
                'output-encoding' => 'utf8'
            );// Fix UTF8 and <br preakpage="true" />
            $sText=$oPDF->fixHTMLCode($sText,$sCssContent,'',$tidy_options);
        }
        else
        {
            // TODO : Find the good way to use pagebreak="true", verify if page is used in tcpdf
            // ALT : explode/implode
            $oPurifier = new CHtmlPurifier();
            $oPurifier->options = array(
                'AutoFormat.RemoveEmpty'=>false,
                'Core.NormalizeNewlines'=>false,
                'CSS.AllowTricky'=>true, // Allow display:none; (and other)
                'CSS.Trusted' => true,
                'Attr.EnableID'=>true, // Allow to set id
                'Attr.AllowedFrameTargets'=>array('_blank','_self'),
                'URI.AllowedSchemes'=>array(
                    'http' => true,
                    'https' => true,
                    'mailto' => true,
                    'ftp' => true,
                    'nntp' => true,
                    'news' => true,
                    'data' => true,
                    )
            );
            $sText=$oPurifier->purify($sText);

        }
        $sText=str_replace($pdfReplaced, $pdfSpecific, $sText);
        $sText="<style>\n{$sCssContent}\n</style>\n$sText\n";
        //~ $this->event->getContent($this)
              //~ ->addContent(htmlentities($sText));
        $aLogo=$this->_getLogoPaths();
        if(!empty($aLogo['path']))
           $oPDF->sLogoFile=$aLogo['path'];

        $oPDF->initAnswerPDF($aSurvey, $aPdfLanguageSettings, $sHeader, $sSubHeader);
        // output the HTML content
        $oPDF->writeHTML($sText, true, false, true, false, '');

        $oPDF->lastPage();
        $sFilePdfName=Yii::app()->getConfig("tempdir").DIRECTORY_SEPARATOR.$this->get("name_{$sType}",null,null,$this->settings["name_{$sType}"]["default"])."_{$this->iSurveyId}_";
        $oSessionSurvey=Yii::app()->session["survey_{$this->iSurveyId}"];
        if(!empty($oSessionSurvey['token']) && $this->get("filenameend",null,null,$this->settings['filenameend']['default'])=='token')
        {
            $sFilePdfName.="{$oSessionSurvey['token']}.pdf";

        }
        else
        {
            $sFilePdfName.="{$this->iResponseId}.pdf";
        }
        $oPDF->Output($sFilePdfName, 'F');
        Yii::log("getPdfFile done for {$iQid} in {$this->iSurveyId}",'trace','application.plugins.sendPdfReport');
        return $sFilePdfName;
    }
    /**
     * Get the logo file name
     * @return string : URI for pdf file
     */
    private function _getLogoPaths($iSurveyId)
    {
        $sLogoName='logo.png'; // @todo search for array (png|jpg|gif)

        if(basename($sLogoName)!=$sLogoName)
        {
            return array('error'=>"Use cleaner file name (no / or \)");
        }
        $ext=strtolower(pathinfo($sLogoName, PATHINFO_EXTENSION));
        if(!in_array($ext,array("png","gif","jpg")))
        {
            return array('error'=>"Please, use only png,gif or jpg image.");
        }
        $uploadBase="../../../upload";
        $sTemplate=Survey::model()->findByPk($iSurveyId)->template;
        if(is_file(Yii::app()->getConfig('uploaddir')."/templates/{$sTemplate}/${sLogoName}"))
        {
            return array(
                'path'=>"{$uploadBase}/templates/{$sTemplate}/${sLogoName}",
                'url'=>Yii::app()->getConfig('uploadurl')."/templates/{$sTemplate}/${sLogoName}",
            );
        }
        if(is_file(Yii::app()->getConfig('uploaddir')."/surveys/{$iSurveyId}/images/{$sLogoName}"))
        {
            return array(
                'path'=>"{$uploadBase}/surveys/{$iSurveyId}/images/{$sLogoName}",
                'url'=>Yii::app()->getConfig('uploadurl')."/surveys/{$iSurveyId}/images/{$sLogoName}",
            );
        }
        if(is_file(Yii::app()->getConfig('uploaddir')."/surveys/{$iSurveyId}/files/{$sLogoName}"))
        {
            return array(
                'path'=>"{$uploadBase}/surveys/{$iSurveyId}/files/{$sLogoName}",
                'url'=>Yii::app()->getConfig('uploadurl')."/surveys/{$iSurveyId}/files/{$sLogoName}",
            );
        }
        if(is_file(Yii::app()->getConfig('uploaddir')."/surveys/{$iSurveyId}/{$sLogoName}"))
        {
            return array(
                'path'=>"{$uploadBase}/surveys/{$iSurveyId}/{$sLogoName}",
                'url'=>Yii::app()->getConfig('uploadurl')."/surveys/{$iSurveyId}/{$sLogoName}",
            );
        }
        return array('error'=>"File not found in your survey.");
    }
}
