<?php
/**
 * pdfReport Plugin for LimeSurvey
 * Use question setings to create a report and send it by email.
 *
 * @author Denis Chenu <https://sondages.pro>
 * @copyright 2015-2017 Denis Chenu <https://sondages.pro>
 * @copyright 2017 Réseau en scène Languedoc-Roussillon <https://www.reseauenscene.fr/>
 * @copyright 2015 Ingeus <http://www.ingeus.fr/>
 * @license AGPL v3
 * @version 1.1.0
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

    /**
     * @var integer $_iSurveyId
     */
    private $_iSurveyId;
    /**
     * @var integer $_iResponseId
     */
    private $_iResponseId;

    /**
     * Register to needed event
     */
    public function init()
    {
        /* Add the attribute */
        $this->subscribe('newQuestionAttributes','addPdfReportAttribute');
        /* Generate and save pdfReport when submit */
        $this->subscribe('afterSurveyComplete', 'afterSurveyComplete');
        /* Remove answers (and help) part */
        $this->subscribe('beforeQuestionRender', 'removeAnswersPart');
        /* To add own translation message source */
        $this->subscribe('afterPluginLoad');
        /* To replace if needed printanswer */
        $this->subscribe('beforeControllerAction', 'setPrintAnswer');
    }

    /**
     * @see ls\pluginmanager\PluginBase->seetings
     */
    protected $settings = array(
        'basesavedirectory'=> array(
            'type'=>'string',
            'label'=>'Directory on the server to move the file (if question settings is set)',
            'help'=>'You can use {SID} for survey id. Plugin didn`t create directory.',
            'default'=>'',
        ),
        'usetokenfilename' => array(
            'type'=>'select',
            'label'=>'Usage of token in filemane',
            'options'=>array(
                'add'=>'Adding at start',
                'alone'=>'Using only token',
                'none'=>'Didn\t use it',
            ),
            'help'=>'For filename generation, way of using token value if exist and not empty.',
            'default'=>'add',
        ),
    );


    /**
     * @see ls\helpers\questionHelper->getAttributesDefinitions()
     */
    public function addPdfReportAttribute()
    {
        $pdfReportAttribute = array(
            'pdfReport'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>1,
                'inputtype'=>'switch',
                'default'=>0,
                'help'=>$this->_translate('The pdf are saved inside question answers, it\'s better if you hide the question, else only answers part are hidden.'),
                'caption'=>$this->_translate('Use this question as pdf report.'),
            ),
            'pdfReportTitle'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>10,
                'inputtype'=>'text',
                'default'=>'{SITENAME}',
                'i18n'=>true,
                'expression'=>1,
                'help'=>'',
                'caption'=>$this->_translate('Title for the pdf.'),
            ),
            'pdfReportSubTitle'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>11,
                'inputtype'=>'text',
                'default'=>'{SURVEYNAME}',
                'i18n'=>true,
                'expression'=>1,
                'help'=>'',
                'caption'=>$this->_translate('Sub title for the pdf.'),
            ),
            'pdfReportPrintAnswer'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>20,
                'inputtype'=>'singleselect',
                'options'=>array(
                    0=>gT('No'),
                    1=>$this->_translate('Allow public print (with good link).'),
                    2=>$this->_translate('Replace public print answer.'),
                ),
                'default'=>0,
                'help'=>$this->_translate('Allow to print answer at end of the survey, see manual for url.Optionnaly replace the default print answer by a dowload link of the pdf.'),
                'caption'=>$this->_translate('Replace public print answer.'),
            ),
            'pdfReportSavedFileName'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>30,
                'inputtype'=>'text',
                'default'=>'',
                'expression'=>1,
                'help'=>$this->_translate('For the name of the uploaded file. By default usage of questioncode.pdf'),
                'caption'=>$this->_translate('Name of saved PDF file.'),
            ),
            'pdfReportSendByEmailMail'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>40,
                'inputtype'=>'text',
                'default'=>'',
                'i18n'=>false,
                'expression'=>1,
                'help'=>$this->_translate('Optionnal email to send pdf Report.'),
                'caption'=>$this->_translate('Send it by email to'),
            ),
            'pdfReportSendByEmailContent'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('pdf report'),
                'sortorder'=>45,
                'inputtype'=>'singleselect',//'buttongroup',
                'options'=>array(
                    'confirm'=>$this->_translate('Confirmation email'),
                    'admin_notification'=>$this->_translate('Basic admin notification'),
                    'admin_responses'=>$this->_translate('Detailed admin notification'),
                ),
                'default'=>'admin_notification',
                'i18n'=>false,
                'expression'=>1,
                'help'=>$this->_translate('This don\'t deactivate limesurvey other email system.'),
                'caption'=>$this->_translate('Content and subject of the email'),
            ),

        );
        if(method_exists($this->getEvent(),'append')) {
            $this->getEvent()->append('questionAttributes', $pdfReportAttribute);
        } else {
            $questionAttributes=(array)$this->event->get('questionAttributes');
            $questionAttributes=array_merge($questionAttributes,$pdfReportAttribute);
            $this->event->set('questionAttributes',$questionAttributes);
        }
    }

    /**
     * Do all the pdf after survey is submitted, and each action if needed
     */
    public function afterSurveyComplete()
    {
        $this->_iSurveyId=$this->getEvent()->get('surveyId');
        $this->_iResponseId=$this->getEvent()->get('responseId');
        $this->doPdfReports();
    }

    /**
     * Do all the pdf after survey is submitted, and each action if needed
     */
    public function removeAnswersPart()
    {
        if($this->getEvent()->get('type')=='|') {
            $oEvent=$this->getEvent();
            $oQuestionPdfReport = intval(QuestionAttribute::model()->count(
                "attribute=:attribute and qid=:qid and value=:value",
                array(':attribute'=>'pdfReport',':qid'=>$oEvent->get('qid'),':value'=>1)
            ));
            if($oQuestionPdfReport) {
                $inputName="{$oEvent->get('surveyId')}X{$oEvent->get('gid')}X{$oEvent->get('qid')}";
                $answers = \CHtml::hiddenField($inputName , '', array('id' => $inputName)) // LS bug : must fix (id starting by number)
                         . \CHtml::hiddenField("{$inputName}_filecount" , '', array('id' => "{$inputName}_filecount"));
                $oEvent->set('answers',$answers);
                $oEvent->set('file_valid_message','');
                $oEvent->set('valid_message','');
                $oEvent->set('class', $oEvent->get('class')." pdfreport-question");
            }
        }
    }

    /**
     * Do all reports needed
     */
    public function doPdfReports()
    {
        // Only in next release $oQuestionAttribute = QuestionAttribute::model()->with('qid')->together()->findAll('sid=:sid and attribute=:attribute and value=:value',array(':sid'=>$iSid,':attribute'=>'pdfReport',':value'=>1));

        $criteria = new CDbCriteria;
        $criteria->join='LEFT JOIN {{questions}} as question ON question.qid=t.qid';
        $criteria->condition='question.sid = :sid and question.language=:language and attribute=:attribute and value=:value';
        $criteria->params=array(':sid'=>$this->_iSurveyId,':language'=>Yii::app()->getLanguage(),':attribute'=>'pdfReport',':value'=>1);
        $oQuestionAttribute = QuestionAttribute::model()->findAll($criteria);
        if($oQuestionAttribute){
            foreach($oQuestionAttribute as $questionAttribute){
                $pdfFile=$this->_getPdfFile($questionAttribute->qid);
                if($pdfFile){
                    $oQuestion=Question::model()->findByPk(array('qid'=>$questionAttribute->qid,'language'=>Yii::app()->getLanguage()));
                    if($oQuestion->type=="|"){
                        $this->_saveInFileUpload($oQuestion);
                        $this->_setSessionPrintAnswer($oQuestion);
                    }
                    $this->_sendByEMail($oQuestion);
                    $this->_saveInDirectory($oQuestion);
                    unlink($pdfFile);
                }
            }
        }
    }

    /**
     * Replace print answer by own donwload
     * @see beforeControllerAction
     */
    public function setPrintAnswer()
    {
        if($this->event->get('controller')=='printanswers')
        {
            $aPdfReportPrintRight=Yii::app()->session["pdfReportPrintRight"];
            $surveyid=Yii::app()->getRequest()->getQuery('surveyid');
            /* find if one question have print settings */
            if(isset($aPdfReportPrintRight[$surveyid]['replace'])) {
                $this->publicPdfDownload($surveyid,$aPdfReportPrintRight[$surveyid]['replace']);
                $this->event->set('run',false);
            }
        }
    }

    /**
     * Pdf download of a upload question type
     * @param int $surveyid
     * @param int $qid
     * @param int $srid : responseId
     * @return void
     */
    public function publicPdfDownload($surveyid,$qid=null,$srid=null){
        $oSurvey=Survey::model()->findByPk($surveyid);
        if(!$oSurvey) {
            throw new CHttpException(404,gT('Invalid survey ID'));
        }
        /* Control if allowed */
        $aSessionPrintRigth=Yii::app()->session["pdfReportPrintRight"];
        $aSurveyPrintRigth=$aSessionPrintRigth[$surveyid];
        if(empty($aSurveyPrintRigth)){
            throw new CHttpException(401, 'You are not allowed to print answers.');
        }
        if(!$srid){
            $srid=$aSurveyPrintRigth['srid'];
        }
        if(!$qid){
            $qid=$aSurveyPrintRigth['replace'];
        }

        // Ok we get the survey and the qid
        $oResponse = Response::model($surveyid)->findByPk($srid);
        $aQuestionFiles=$oResponse->getFiles($qid);
        if(!$aQuestionFiles) {
            throw new CHttpException(404,gT("Sorry, this file was not found."));
        }
        $aFile=$aQuestionFiles[0];
        $sFileRealName = Yii::app()->getConfig('uploaddir') . "/surveys/" . $iSurveyId . "/files/" . $aFile['filename'];
        if (file_exists($sFileRealName)) {
            $mimeType=CFileHelper::getMimeType($sFileRealName, null, false);
            if(is_null($mimeType)){
                $mimeType="application/octet-stream";
            }
            @ob_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: '.$mimeType);
            header('Content-Disposition: attachment; filename="' . rawurldecode($aFile['name']) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($sFileRealName));
            readfile($sFileRealName);
            Yii::app()->end();
        }
        throw new CHttpException(404,gT("Sorry, this file was not found."));
    }
    /**
     * set session for print answer to this question if settings
     * @param Question $oQuestion
     * @return void
     */
    private function _setSessionPrintAnswer($oQuestion)
    {

        $oQuestionAttribute = QuestionAttribute::model()->find(
            "attribute=:attribute and qid=:qid and value>0",
            array(':attribute'=>'pdfReportReplacePrintAnswer',':qid'=>$oQuestion->qid)
        );
        if(!$oQuestionAttribute){
            return;
        }
        $aSessionPrintRigth=Yii::app()->session["pdfReportPrintRight"];
        if(empty($aSessionPrintRigth)) {
            $aSessionPrintRigth=array();
        }
        if(empty($aSessionPrintRigth)) {
            $aSessionPrintRigth[$oQuestion->sid]=array(
                'srid'=>$this->_iResponseId,
                'allowed'=>array(),
            );
        }
        /* Always add it to allowed */
        $aSessionPrintRigth[$oQuestion->sid]['allowed'][]=$oQuestion->qid;
        /* Optionnally set it to replace */
        if($oQuestionAttribute->value==2){
            $aSessionPrintRigth[$oQuestion->sid]['replace']=$oQuestion->qid;
        }
        Yii::app()->session["pdfReportPrintRight"]=$aSessionPrintRigth;
    }
    /**
     * Get a pdf file from a string
     * @param integer $iQid
     * @return string : URI for pdf file
     */
    private function _getPdfFile($iQid)
    {
        $oQuestion=Question::model()->findByPk(array('qid'=>$iQid,'language'=>Yii::app()->getLanguage()));
        if(!$oQuestion){
            Yii::log("Question number {$iQid} invalid",'error','application.plugins.sendPdfReport');
            return null;
        }
        $iSurveyId=$this->_iSurveyId;
        $iResponseId=$this->_iResponseId;


        $sText=$oQuestion->question;
        $aQuestionsAttributes=QuestionAttribute::model()->getQuestionAttributes($iQid,Yii::app()->getLanguage());
        $sHeader=trim($aQuestionsAttributes['pdfReportTitle'][Yii::app()->getLanguage()]);
        $sSubHeader=trim($aQuestionsAttributes['pdfReportSubTitle'][Yii::app()->getLanguage()]);

        $sText=$this->_EMProcessString($sText);
        $sHeader=$this->_EMProcessString($sHeader);
        $sSubHeader=$this->_EMProcessString($sSubHeader);

        //~ return;
        $sCssContent=$this->_getCss();
        $sHeader=strip_tags($sHeader);
        $sSubHeader=strip_tags($sSubHeader);

        $aSurvey=getSurveyInfo($this->_iSurveyId,Yii::app()->getLanguage());
        $sSurveyName = $aSurvey['surveyls_title'];
        if (!defined('K_PATH_IMAGES')) {
            define('K_PATH_IMAGES', '');
        }
        Yii::setPathOfAlias('sendPdfReport', dirname(__FILE__));
        //define('K_PATH_IMAGES', Yii::app()->getConfig("homedir").DIRECTORY_SEPARATOR);

        Yii::import('application.libraries.admin.pdf', true);
        Yii::import('application.helpers.pdfHelper');
        Yii::import("sendPdfReport.helpers.pdfReportHelper");

        $aPdfLanguageSettings=pdfHelper::getPdfLanguageSettings(Yii::app()->getLanguage());

        $oPDF = new pdfReportHelper();
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
        $aLogo=$this->_getLogoPaths($this->_iSurveyId);
        if(!empty($aLogo['path'])){
           $oPDF->sLogoFile=$aLogo['path'];
        }
        $oPDF->initAnswerPDF($aSurvey, $aPdfLanguageSettings, $sHeader, $sSubHeader);
        // output the HTML content
        $oPDF->writeHTML($sText, true, false, true, false, '');

        $oPDF->lastPage();
        $sFilePdfName=$this->_getPdfFileName($oQuestion->title);

        $oPDF->Output($sFilePdfName, 'F');
        Yii::log("getPdfFile done for {$iQid} in {$this->_iSurveyId}",'trace','application.plugins.sendPdfReport');
        return $sFilePdfName;
    }

    /**
     * Get the logo file name
     * @return string : URI for pdf file
     */
    private function _getLogoPaths()
    {
        $aLogoNames=array(
            'pdflogo.png',
            'pdflogo.jpg',
            'pdflogo.gif',
        );
        $surveyUploadDir=Yii::app()->getConfig('uploaddir')."/surveys/".$this->_iSurveyId;
        $surveyUploadUrl=Yii::app()->getConfig('uploadurl')."/surveys/".$this->_iSurveyId;
        $oTemplate = \Template::model()->getInstance(null, $this->_iSurveyId);
        $oSurvey=Survey::model()->findByPk($this->_iSurveyId);
        $templateUploadDir=$oTemplate->filesPath;
        $templateUploadUrl = Template::getTemplateURL($oSurvey->template)."/";
        $templateUploadUrl.= isset($oTemplate->config->engine->filesdirectory)? $oTemplate->config->engine->filesdirectory."/":"";
        $aDirectories=array(
            array(
                'path'=>$surveyUploadDir."/files/",
                'url'=>$surveyUploadUrl."/files/",
            ),
            array(
                'path'=>$surveyUploadDir."/images/",
                'url'=>$surveyUploadUrl."/images/",
            ),
            array(
                'path'=>$templateUploadDir,
                'url'=>$templateUploadUrl,
            ),
        );
        foreach($aDirectories as $aDir) {
            foreach($aLogoNames as $sLogoName) {
                if(is_file($aDir['path'].$sLogoName))
                {
                    return array(
                        'path'=>$aDir['path'].$sLogoName,
                        'url'=>$aDir['url'].$sLogoName,
                    );
                }
            }
        }

        return array('error'=>"File not found in your survey.");
    }

    /**
     * Save the generated file in file upload
     * @todo
     * @param object question object
     * @return void
     */
    private function _saveInDirectory($oQuestion)
    {

    }
    /**
     * Save the generated file in file upload
     * @param object question object
     * @return void
     */
    private function _saveInFileUpload($oQuestion)
    {
        if($oQuestion->type!='|'){
            return;
        }
        $oSurvey=Survey::model()->findByPk($this->_iSurveyId);
        if(!$oSurvey || $oSurvey->active!='Y'){
            return;
        }
        $sAnswerColumn="{$this->_iSurveyId}X{$oQuestion->gid}X{$oQuestion->qid}";
        $sAnswerCountColumn= "{$sAnswerColumn}_filecount";
        $uploadSurveyDir=App()->getConfig("uploaddir").DIRECTORY_SEPARATOR."surveys".DIRECTORY_SEPARATOR.$this->_iSurveyId.DIRECTORY_SEPARATOR."files".DIRECTORY_SEPARATOR;
        if(!is_dir($uploadSurveyDir)) {
            mkdir($uploadSurveyDir, 0777, true);
        }
        $fileName=$this->_getPdfFileName($oQuestion->title);
        $fileSize=0.001 * filesize($fileName); // Same than controller
        $oQuestionAttribute = QuestionAttribute::model()->find(
            "attribute=:attribute and qid=:qid",
            array(':attribute'=>'pdfReportSavedFileName',':qid'=>$oQuestion->qid)
        );
        if($oQuestionAttribute && trim($oQuestionAttribute->value)) {
            $reportSavedFileName=$this->_EMProcessString(trim($oQuestionAttribute->value)).".pdf";
        } else {
            $reportSavedFileName="{$oQuestion->title}.pdf";
        }
        $sDestinationFileName = 'fu_' . hexdec(crc32($this->_iResponseId.rand ( 1 , 10000 ).$oQuestion->title));
        if (!copy($fileName, $uploadSurveyDir . $sDestinationFileName)) {
            Yii::log("Error moving file $fileName to $uploadSurveyDir",'error','application.plugins.pdfReport');
            return;
        }
        $aAnswer=array(
            array(
                'title'=>'',
                'comment'=>'',
                'size'=>$fileSize,
                'filename'=>$sDestinationFileName,
                'name'=>$reportSavedFileName,
                'ext'=>'pdf'
            )
        );
        $oResponse=Response::model($this->_iSurveyId)->find('id=:id',array(':id'=>$this->_iResponseId));
        $oResponse->$sAnswerColumn=ls_json_encode($aAnswer);
        $oResponse->$sAnswerCountColumn=1;
        if(!$oResponse->save()){
            Yii::log($oResponse->getErrors(),'error','application.plugins.pdfReport');
        }
    }

    /**
     * Save the pdf by email
     * @param
     * @retuen void
     */
    private function _sendByEmail($oQuestion)
    {
        $aQuestionsAttributes=QuestionAttribute::model()->getQuestionAttributes($oQuestion->qid,Yii::app()->getLanguage());
        $questionAttributeEmails=trim($aQuestionsAttributes['pdfReportSendByEmailMail']);
        if($questionAttributeEmails==""){
            return;
        }
        $questionAttributeEmails=$this->_EMProcessString($questionAttributeEmails);
        $aRecipient=explode(";", $questionAttributeEmails);
        $aValidRecipient=array();
        foreach($aRecipient as $sRecipient)
        {
            $sRecipient=trim($sRecipient);
            if(validateEmailAddress($sRecipient))
            {
                $aValidRecipient[]=$sRecipient;
            }
        }
        $oSurvey=Survey::model()->findByPk($this->_iSurveyId);
        $aMessage=$this->_getEmailContent($aQuestionsAttributes['pdfReportSendByEmailContent']);
        $sFile=$this->_getPdfFileName($oQuestion->title);
        $aAttachments = array($this->_getPdfFileName($oQuestion->title));
        foreach ($aValidRecipient as $sRecipient)
        {
            if (!SendEmailMessage($aMessage['message'], $aMessage['subject'],$sRecipient,"{$oSurvey->admin} <{$oSurvey->adminemail}>" , Yii::app()->getConfig("sitename"), true, getBounceEmail($this->_iSurveyId), $aAttachments))
            {
                Yii::log("Email with ".$sFile." can not be sent due to a mail error",'error','application.plugins.pdfReport');
            }
            else
            {
                Yii::log("Email with ".$sFile." sent",'info','application.plugins.pdfReport');
            }
        }

    }
    /**
     * Generate unique pdf filename
     * @param string $qCode question code
     * @param boolean $onlyFile return only the file name
     * @return string URI
     */
    private function _getPdfFileName($qCode,$onlyFile=false)
    {
        $aFilePdfName=array(
            $qCode,
            $this->_iSurveyId,
        );
        /* For unicity : make an unique responseId big number : only for testing or deactivated survey*/
        if(empty($this->_iResponseId)){
            $this->_iResponseId=hexdec(crc32(time().rand ( 1 , 1000 )));
        }
        if(!empty($_SESSION["survey_{$this->_iSurveyId}"]['token']) && $this->get("usetokenfilename",null,null,$this->settings['usetokenfilename']['default'])!=='none') {
            $aFilePdfName[]=$_SESSION["survey_{$this->_iSurveyId}"]['token'];
            if($this->get("usetokenfilename",null,null,$this->settings['usetokenfilename']['default'])==!'alone'){
                $aFilePdfName[]=$this->_iResponseId;
            }
        } else {
             $aFilePdfName[]=$this->_iResponseId;
        }
        $sPdfFileName=implode("_",$aFilePdfName);
        $sPdfFileName.=".pdf";
        if($onlyFile) {
            return $sPdfFileName;
        } else {
            return Yii::app()->getRuntimePath()."/".$sPdfFileName;
        }
    }

    /**
     * Get fixed content by email
     */
    private function _getEmailContent($sType)
    {
        $aReplacementVars=$this->_getReplacementVars($sType=='confirm');
        $aSurvey=getSurveyInfo($this->_iSurveyId,Yii::app()->language);
        $aReData=array(
            'saved_id'=>$this->_iResponseId,
            'thissurvey'=>$aSurvey,
        );
        $sSubject=templatereplace($aSurvey["email_{$sType}_subj"],$aReplacementVars,$aReData,'',false,null,array(),true);
        $sMessage=templatereplace($aSurvey["email_{$sType}"],$aReplacementVars,$aReData,'',false,null,array(),true);

        return array(
            'subject'=>$sSubject,
            'message'=>$sMessage,
        );
    }
    /**
     * Get the replacement var for email
     * @param boolean : wit or wothout token value
     * @return string[]
     */
    private function _getReplacementVars()
    {
        $thissurvey=$aSurvey=getSurveyInfo($this->_iSurveyId,Yii::app()->language);
        $aReplacementVars=array();
        $aReplacementVars['RELOADURL']='';
        $aReplacementVars['ADMINNAME'] = $aSurvey['adminname'];
        $aReplacementVars['ADMINEMAIL'] = $aSurvey['adminemail'];
        $aReplacementVars['VIEWRESPONSEURL']=Yii::app()->createAbsoluteUrl("/admin/responses/sa/view/surveyid/{$this->_iSurveyId}/id/{$this->_iResponseId}");
        $aReplacementVars['EDITRESPONSEURL']=Yii::app()->createAbsoluteUrl("/admin/dataentry/sa/editdata/subaction/edit/surveyid/{$this->_iSurveyId}/id/{$this->_iResponseId}");
        $aReplacementVars['STATISTICSURL']=Yii::app()->createAbsoluteUrl("/admin/statistics/sa/index/surveyid/{$this->_iSurveyId}");
        // Always HTML, TODO : fix it
        if (true)
        {
            $aReplacementVars['VIEWRESPONSEURL']="<a href='{$aReplacementVars['VIEWRESPONSEURL']}'>{$aReplacementVars['VIEWRESPONSEURL']}</a>";
            $aReplacementVars['EDITRESPONSEURL']="<a href='{$aReplacementVars['EDITRESPONSEURL']}'>{$aReplacementVars['EDITRESPONSEURL']}</a>";
            $aReplacementVars['STATISTICSURL']="<a href='{$aReplacementVars['STATISTICSURL']}'>{$aReplacementVars['STATISTICSURL']}</a>";
        }
        $aReplacementVars['ANSWERTABLE']='';
        $oSessionSurvey=Yii::app()->session["survey_{$this->_iSurveyId}"];
        if($thissurvey['anonymized'] != 'Y' && !empty($oSessionSurvey['token']) && tableExists('{{tokens_' . $this->_iSurveyId . '}}'))
        {
            $oToken=Token::model($this->_iSurveyId)->find("token=:token",array('token' => $oSessionSurvey['token']));
            if($oToken)
            {
                foreach($oToken->attributes as $attribute=>$value)
                {
                    $aReplacementVars[strtoupper($attribute)]=$value;
                }
            }
        }
        return $aReplacementVars;
    }

    /**
     * get css for this survey
     * @return string : css
     */
    private function _getCss()
    {
        $oTemplate = \Template::model()->getInstance(null, $this->_iSurveyId);
        if(is_file($oTemplate->filesPath.'pdfreport.css')){
            return file_get_contents($oTemplate->filesPath.'/pdfreport.css');
        }
        return file_get_contents(dirname(__FILE__).'/base.css');
    }
    /**
     * Translate a plugin string
     * @param string $string to translate
     * @return string
     */
    private function _translate($string){
        return Yii::t('',$string,array(),'pdfReport');
    }

    /**
     * Add this translation just after loaded all plugins
     * @see event afterPluginLoad
     */
    public function afterPluginLoad(){
        // messageSource for this plugin:
        $pdfReportLang=array(
            'class' => 'CGettextMessageSource',
            'cacheID' => 'pdfReportLang',
            'cachingDuration'=>3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR.'locale',
            'catalog'=>'messages',// default from Yii
        );
        Yii::app()->setComponent('pdfReport',$pdfReportLang);
    }

    /**
     * Process a string via expression manager (static way)
     * @param string $string
     * @return string
     */
    private function _EMProcessString($string)
    {
        Yii::app()->setConfig('surveyID',$this->_iSurveyId);
        $oSurvey=Survey::model()->findByPk($this->_iSurveyId);
        $replacementFields=array(
            'SAVEDID'=>$this->_iResponseId,
            'SITENAME'=>App()->getConfig('sitename'),
            'SURVEYNAME'=>$oSurvey->getLocalizedTitle(),
        );
        return \LimeExpressionManager::ProcessString($string, null, $replacementFields, false, 3, 0, false, false, true);
    }
}
