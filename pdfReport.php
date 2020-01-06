<?php
/**
 * pdfReport Plugin for LimeSurvey
 * Use question settings to create a report and send it by email.
 *
 * @author Denis Chenu <https://sondages.pro>
 * @copyright 2015-2019 Denis Chenu <https://sondages.pro>
 * @copyright 2017 Réseau en scène Languedoc-Roussillon <https://www.reseauenscene.fr/>
 * @copyright 2015 Ingeus <http://www.ingeus.fr/>
 * @license AGPL v3
 * @version 1.9.0
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

class pdfReport extends PluginBase {
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
        /* Allow printing on current */
        $this->subscribe('newDirectRequest');
        /* To replace if needed printanswer */
        $this->subscribe('beforeControllerAction', 'setPrintAnswer');
    }

    /**
     * @see ls\pluginmanager\PluginBase->seetings
     */
    protected $settings = array(
        'usetokenfilename' => array(
            'type'=>'select',
            'label'=>'Usage of token in filemane',
            'options'=>array(
                'add'=>'Adding at start',
                'alone'=>'Using only token',
                'none'=>'Didn‘t use it',
            ),
            'help'=>'For filename generation, way of using token value if exist and not empty.',
            'default'=>'add',
        ),
        'basicDocumentation'=>array(
            'type'=>'info',
            'content'=>'<div class="well">To allow user to get the file of the question number X at end : you can use this url:</div>',
        ),
        /* This part is not active currently */
        //~ 'basesavedirectory'=> array(
            //~ 'type'=>'string',
            //~ 'label'=>'Directory on the server to move the file (if question settings is set)',
            //~ 'help'=>'You can use {SID} for survey id. Plugin didn`t create directory.',
            //~ 'default'=>'',
        //~ ),
        //~ 'usetokenfilename' => array(
            //~ 'type'=>'select',
            //~ 'label'=>'Usage of token in filemane',
            //~ 'options'=>array(
                //~ 'add'=>'Adding at start',
                //~ 'alone'=>'Using only token',
                //~ 'none'=>'Didn\t use it',
            //~ ),
            //~ 'help'=>'For filename generation, way of using token value if exist and not empty.',
            //~ 'default'=>'add',
        //~ ),
    );

    /**
     * @see getPluginSettings
     */
    public function getPluginSettings($getValues=true)
    {
        $dowloadurl=Yii::app()->getController()->createUrl('plugins/direct', array('plugin' => $this->getName(), 'surveyid' => 'SID','qid'=>'QID'));
        $dowloadurl=str_replace(array("SID","QID"),array("{SID}","{QID}"),$dowloadurl);
        $helpString=sprintf($this->_translate("To allow user to get the file of the question number X at end : you can use this url: %s. Replacing %s by the question number (LimeSurvey replace %s by the survey number)."),"<code>".$dowloadurl."</code>","<code>{QID}</code>","{SID}");
        $this->settings['basicDocumentation']['content']="<div class='well'>{$helpString}</div>";

        return parent::getPluginSettings($getValues);
    }


    /**
     * @see questionHelper->getAttributesDefinitions()
     */
    public function addPdfReportAttribute()
    {
        $pdfReportAttribute = array(
            'pdfReport'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>1,
                'inputtype'=>'switch',
                'default'=>0,
                'help'=>$this->_translate('The pdf are saved inside question answers, it‘s better if you hide the question, else only answers part are hidden.'),
                'caption'=>$this->_translate('Use this question as pdf report.'),
            ),
            'pdfReportTitle'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
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
                'category'=>$this->_translate('PDF report'),
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
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>20,
                'inputtype'=>'singleselect',
                'options'=>array(
                    0=>gT('No'),
                    1=>$this->_translate('Allow public download (with the link).'),
                    2=>$this->_translate('Replace public print answer.'),
                ),
                'default'=>0,
                'help'=>$this->_translate('Allow to download pdf after submitted the survey, see plugin settings for url.Optionnaly replace the default print answer by a dowload link of the pdf.'),
                'caption'=>$this->_translate('Replace public print answer.'),
            ),
            'pdfReportSavedFileName'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>30,
                'inputtype'=>'text',
                'default'=>'',
                'i18n'=>true,
                'htmlOptions'=>array(
                    'placeholder'=>'questioncode',
                ),
                'expression'=>1,
                'help'=>$this->_translate('By default usage of the question code. You don‘t have to put the .pdf part.'),
                'caption'=>$this->_translate('Name of saved PDF file.'),
            ),
            'pdfReportSanitizeSavedFileName'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>30,
                'inputtype'=>'singleselect',//'buttongroup',
                'options'=>array(
                    'none'=>$this->_translate('No filter'),
                    'base'=>$this->_translate('Basic filter'),
                    'alphanumeric'=>$this->_translate('Alphanumeric only'),
                    'alphanumericlower'=>$this->_translate('Alphanumeric and lower case'),
                ),
                'default'=>'base',
                'help'=>$this->_translate('Basic filter try to remove invalid an dangerous character, use “no filter” with caution. If there are a filter, filename is limited to 254 character. With alphanumeric space was replaced by -.'),
                'caption'=>$this->_translate('Sanitization of the file name.'),
            ),
            'pdfReportSendByEmailMail'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
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
                'category'=>$this->_translate('PDF report'),
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
                'help'=>$this->_translate('This don‘t deactivate limesurvey other email system.'),
                'caption'=>$this->_translate('Content and subject of the email'),
            ),
            'pdfReportSendByEmailAttachment'=>array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>50,
                'inputtype'=>'switch',
                'default'=>1,
                'help'=>$this->_translate('Add existing attachements of the email templates from LimeSurvey.'),
                'caption'=>$this->_translate('Add attachements of email'),
            ),
        );
        if(Yii::getPathOfAlias("limeMpdf")) {
            $pdfReportAttribute['pdfReportPdfGenerator'] = array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>100,
                'inputtype'=>'switch',
                'default'=>1,
                'help'=>$this->_translate('You have limeMpdf plugin allowing more class, but don‘t use pdfreport.css. Then if you need usage of pdfreport.css: you can choose to use old tcpdf system.'),
                'caption'=>$this->_translate('Use limeMpdf'),
            );
            $pdfReportAttribute['pdfReportCreateToc'] = array(
                'types'=>'|', /* upload question type */
                'category'=>$this->_translate('PDF report'),
                'sortorder'=>110,
                'inputtype'=>'switch',
                'default'=>1,
                'help'=>$this->_translate('Plugin limeMpdf allow table of content using h1, h2 etc … then adding title in your pdf set a table of contents.'),
                'caption'=>$this->_translate('Create a PDF table of content'),
            );
        }
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
        $this->_iSurveyId = $this->getEvent()->get('surveyId');
        $this->_iResponseId = $this->getEvent()->get('responseId');
        $this->doPdfReports();
    }

    /**
     * Do all the pdf after survey is submitted, and each action if needed
     */
    public function removeAnswersPart()
    {
        if($this->getEvent()->get('type')=='|') {
            $oEvent=$this->getEvent();
            $oQuestionPdfReport = QuestionAttribute::model()->find(
                "attribute=:attribute and qid=:qid",
                array(':attribute'=>'pdfReport',':qid'=>$oEvent->get('qid'))
            );
            if($oQuestionPdfReport && intval($oQuestionPdfReport->value)) {
                $inputName="{$oEvent->get('surveyId')}X{$oEvent->get('gid')}X{$oEvent->get('qid')}";
                $answers = \CHtml::hiddenField($inputName , '', array('id' => $inputName)) // LS bug : must fix (id starting by number)
                         . \CHtml::hiddenField("{$inputName}_filecount" , 0, array('id' => "{$inputName}_filecount"));
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
        $criteria->params=array(':sid'=>$this->_iSurveyId,':language'=>Yii::app()->getLanguage(),':attribute'=>'pdfReport',':value'=>'1');
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

    public function newDirectRequest()
    {
        $oEvent = $this->event;
        if ($oEvent->get('target') != get_class()) {
          return;
        }
        $surveyid = Yii::app()->getRequest()->getParam("surveyid",Yii::app()->getRequest()->getParam("sid"));
        $oSurvey=Survey::model()->findByPk($surveyid);
        if(!$oSurvey) {
            throw new CHttpException(404,gT('Invalid survey ID'));
        }
        $qid = Yii::app()->getRequest()->getParam("qid");
        if(empty($qid)) {
            throw new CHttpException(400);
        }
        $aAllowAttribute = \QuestionAttribute::model()->find("qid = :qid AND attribute = :attribute",array(":qid"=>$qid,":attribute"=>'pdfReportPrintAnswer'));
        if(empty($aAllowAttribute) || empty($aAllowAttribute->value)) {
            throw new CHttpException(403);
        }
        /* Multi srid allowed */
        $srid = Yii::app()->getRequest()->getParam("srid");
        $currentSrid = isset($_SESSION['survey_'.$surveyid]['srid']) ? $_SESSION['survey_'.$surveyid]['srid'] : null;
        $allowedSrid = null;
        $aSessionPrintRigth=Yii::app()->session["pdfReportPrintRight"];
        if(!empty($aSessionPrintRigth[$surveyid])) {
            $allowedSrid = $aSessionPrintRigth[$surveyid]['srid'];
        }
        if(!empty($srid)) {
            if($srid != $currentSrid && $srid != $allowedSrid && !Permission::model()->hasSurveyPermission($surveyid,'reponse','read')) {
                throw new CHttpException(401);
            }
        }
        if(empty($srid)) {
            $srid = empty($currentSrid) ? $allowedSrid : $currentSrid;
        }
        if(empty($srid)) {
            throw new CHttpException(400,'Survey must be activated');
        }
        $oResponse = Response::model($surveyid)->findByPk($srid);
        $aQuestionFiles = $oResponse->getFiles($qid);
        if(Yii::app()->getRequest()->getParam("reset") || !$aQuestionFiles) {
            $this->_iSurveyId = $surveyid;
            $this->_iResponseId = $srid;
            $this->doPdfReports();
        }
        $this->publicPdfDownload($surveyid,$qid,$srid);
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
        $currentSrid = isset($_SESSION['survey_'.$surveyid]['srid']) ? $_SESSION['survey_'.$surveyid]['srid'] : null;
        $aSessionPrintRigth=Yii::app()->session["pdfReportPrintRight"];
        $allowedSrid = isset($aSessionPrintRigth[$surveyid]['srid']) ? $aSessionPrintRigth[$surveyid]['srid'] : null;
        if(!empty($srid)) {
            if($srid != $currentSrid && $srid != $allowedSrid && !Permission::model()->hasSurveyPermission($surveyid,'reponse','read')) {
                throw new CHttpException(401);
            }
        } else {
            $srid = $allowedSrid ? $allowedSrid : $currentSrid;
        }
        if (empty($srid)) {
            throw new CHttpException(400);
        }
        if (!$qid) {
            $qid=$aSurveyPrintRigth['replace'];
        }

        // Ok we get the survey and the qid
        $oResponse = Response::model($surveyid)->findByPk($srid);
        $aQuestionFiles=$oResponse->getFiles($qid);
        if(!$aQuestionFiles) {
            throw new CHttpException(404,gT("Sorry, this file was not found."));
        }
        $aFile=$aQuestionFiles[0];
        $sFileRealName = Yii::app()->getConfig('uploaddir') . "/surveys/" . $surveyid . "/files/" . $aFile['filename'];
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
            "attribute=:attribute and qid=:qid",
            array(':attribute'=>'pdfReportPrintAnswer',':qid'=>$oQuestion->qid)
        );
        if(!$oQuestionAttribute){
            return;
        }
        if(!intval($oQuestionAttribute->value)){
            return;
        }
        $aSessionPrintRigth=Yii::app()->session["pdfReportPrintRight"];
        if(empty($aSessionPrintRigth)) {
            $aSessionPrintRigth=array();
        }
        /* reset for new srid */
        if(!empty($aSessionPrintRigth[$oQuestion->sid]) && $aSessionPrintRigth[$oQuestion->sid]['srid'] != $this->_iResponseId) {
            $aSessionPrintRigth=array();
        }
        if(empty($aSessionPrintRigth[$oQuestion->sid])) {
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

        $aQuestionsAttributes=QuestionAttribute::model()->getQuestionAttributes($iQid,Yii::app()->getLanguage());
        if(empty($aQuestionsAttributes['pdfReportPdfGenerator'])) {
            return $this->_tcpdfGenerator($oQuestion,$aQuestionsAttributes);
        }
        return $this->_mpdfGenerator($oQuestion,$aQuestionsAttributes);
    }
    private function _mpdfGenerator($oQuestion,$aQuestionsAttributes)
    {
        $sText = $oQuestion->question;
        $sHeader = trim($aQuestionsAttributes['pdfReportTitle'][Yii::app()->getLanguage()]);
        $sSubHeader = trim($aQuestionsAttributes['pdfReportSubTitle'][Yii::app()->getLanguage()]);
        $sText = $this->_EMProcessString($sText,$oQuestion->qid);
        $sHeader = $this->_EMProcessString($sHeader,$oQuestion->qid);
        $sSubHeader = $this->_EMProcessString($sSubHeader,$oQuestion->qid);
        /* tcpd use br, mpdf use pagebreak */
        $sText=str_replace(
            array('<br pagebreak="true" />','<br pagebreak="true"/>','<br pagebreak="true">','<page>','</page>'),
            array('<pagebreak>','<pagebreak>','<pagebreak>','','<pagebreak>'),
            $sText
        );

        /* OK, we go */
        $pdfHelper = new \limeMpdf\helper\limeMpdfHelper($this->_iSurveyId);
        $extraOtions = array();
        if($aQuestionsAttributes['pdfReportCreateToc']) {
            $extraOtions['h2bookmarks'] = Yii::app()->getConfig('pdfReportToc',array('H1'=>0, 'H2'=>1, 'H3'=>2));
            $extraOtions['h2toc'] = Yii::app()->getConfig('pdfReportToc',array('H1'=>0, 'H2'=>1, 'H3'=>2));
        }
        if(!empty($extraOtions)) {
            $pdfHelper->setOptions($extraOtions);
        }
        $pdfHelper->setTitle($sHeader,$sSubHeader);
        $sFilePdfName=$this->_getPdfFileName($oQuestion->title);
        $pdfHelper->filename = $sFilePdfName;
        $pdfHelper->doPdfContent($sText,\Mpdf\Output\Destination::FILE);
        return $sFilePdfName;
    }
    private function _tcpdfGenerator($oQuestion,$aQuestionsAttributes)
    {
        $sText = $oQuestion->question;
        $sHeader = trim($aQuestionsAttributes['pdfReportTitle'][Yii::app()->getLanguage()]);
        $sSubHeader = trim($aQuestionsAttributes['pdfReportSubTitle'][Yii::app()->getLanguage()]);

        $sText = $this->_EMProcessString($sText,$oQuestion->qid);
        $sHeader = $this->_EMProcessString($sHeader,$oQuestion->qid);
        $sSubHeader = $this->_EMProcessString($sSubHeader,$oQuestion->qid);

        $sCssContent=$this->_getCss();
        $sHeader=strip_tags($sHeader);
        $sSubHeader=strip_tags($sSubHeader);
        $aSurvey=getSurveyInfo($this->_iSurveyId,Yii::app()->getLanguage());
        $sSurveyName = $aSurvey['surveyls_title'];
        if (!defined('K_PATH_IMAGES')) {
            define('K_PATH_IMAGES', '');
        }
        Yii::setPathOfAlias('sendPdfReport', dirname(__FILE__));

        Yii::import('application.libraries.admin.pdf', true);
        Yii::import('application.helpers.pdfHelper');
        Yii::import("sendPdfReport.helpers.pdfReportHelper");

        $aPdfLanguageSettings=pdfHelper::getPdfLanguageSettings(Yii::app()->getLanguage());

        $oPDF = new pdfReportHelper();
        $oPDF->sImageBlank = realpath(dirname(__FILE__))."/blank.png";
        $oPDF->sAbsoluteUrl = App()->request->getHostInfo();
        $oPDF->sAbsolutePath = dirname(Yii::app()->request->scriptFile);
        //~ $oPDF->SetCellPadding(0);
        $tagvs = array(
            'p' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            'div' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            'ul' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            'li' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            'pre' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            //~ 'h1' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            //~ 'h2' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            //~ 'h3' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            //~ 'h4' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            //~ 'h5' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
            //~ 'h6' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
        );
        if(!empty(App()->getConfig("pdfreport_tagsv"))) {
            $tagvs = array_merge($tagvs,App()->getConfig("pdfreport_tagsv"));
        }
        $oPDF->setHtmlVSpace($tagvs);
        $pdfSpecific=array('<br pagebreak="true" />','<br pagebreak="true"/>','<br pagebreak="true">','<page>','</page>');
        $pdfReplaced=array('<span>br pagebreak="true"</span>','<span>br pagebreak="true"</span>','<span>br pagebreak="true"</span>','<span>page</span>','<span>/page</span>');
        $sText=str_replace($pdfSpecific, $pdfReplaced, $sText);
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

        $sText=str_replace($pdfReplaced, $pdfSpecific, $sText);
        $sText="<style>\n{$sCssContent}\n</style>\n$sText\n";

        $aLogo=$this->_getLogoPaths($this->_iSurveyId);
        if(!empty($aLogo['path'])){
           $oPDF->sLogoFile=$aLogo['path'];
        }
        $oPDF->initAnswerPDF($aSurvey, $aPdfLanguageSettings, $sHeader, $sSubHeader);
        // output the HTML content
        $errorReporting = error_reporting();
        error_reporting(0);
        $oPDF->writeHTML($sText, true, false, true, false, '');
        error_reporting($errorReporting);

        $oPDF->lastPage();

        $sFilePdfName=$this->_getPdfFileName($oQuestion->title);
        $oPDF->Output($sFilePdfName, 'F');

        Yii::log("getPdfFile done for {$oQuestion->qid} in {$this->_iSurveyId} with tcpdf.",'trace','application.plugins.sendPdfReport');
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
    private function _saveInDirectory($oQuestion) {

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

        $reportSavedFileName = $this->_getPdfSavedFileName($oQuestion);

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
        $questionAttributeEmails=$this->_EMProcessString($questionAttributeEmails,$oQuestion->qid);
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
        $aAttachments = array(array(
            $this->_getPdfFileName($oQuestion->title),
            $this->_getPdfSavedFileName($oQuestion),
        ));
        /* Add LS attachments */
        if($aQuestionsAttributes['pdfReportSendByEmailAttachment']) {
            $aAttachments = array_merge($this->_getEmailAttachements($aQuestionsAttributes['pdfReportSendByEmailContent']),$aAttachments);
        }
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
        if($onlyFile) {
            return $sPdfFileName;
        } else {
            return Yii::app()->getRuntimePath()."/".$sPdfFileName;
        }
    }

    private function _getPdfSavedFileName($oQuestion)
    {
        $oQuestionAttribute = QuestionAttribute::model()->find(
            "attribute=:attribute and qid=:qid",
            array(':attribute'=>'pdfReportSavedFileName',':qid'=>$oQuestion->qid)
        );
        $reportSavedFileName = "{$oQuestion->title}.pdf";
        if(!empty($oQuestionAttribute->value) && trim($oQuestionAttribute->value) !="") {
            $reportSavedFileName = $this->_EMProcessString(trim($oQuestionAttribute->value),$oQuestion->qid);
            $oQuestionAttributeFilter = QuestionAttribute::model()->find(
                "attribute=:attribute and qid=:qid",
                array(':attribute'=>'pdfReportSanitizeSavedFileName',':qid'=>$oQuestion->qid)
            );
            $sanitize = empty($oQuestionAttributeFilter) ? 'base' : $oQuestionAttributeFilter->value;
            $alphanumeric = false;
            $lowercase = false;
            $beautify = false;
            switch($sanitize) {
                case "none":
                    break;
                case "alphanumericlower":
                    $lowercase = true;
                case "alphanumeric":
                    $reportSavedFileName = preg_replace('/\s+/', App()->getConfig("pdfReportSpaceFilename","-"), $reportSavedFileName);
                    /* replace accent see https://stackoverflow.com/a/16022459/2239406 */
                    $reportSavedFileName = $this->_transliterate($reportSavedFileName);
                    /* beautify_filename from santitize set lowercase : we don't want this … */
                    $reportSavedFileName = preg_replace(array('/ +/','/_+/','/-+/'), '-', $reportSavedFileName);
                    $reportSavedFileName = preg_replace(array('/-*\.-*/','/\.{2,}/'), '.', $reportSavedFileName);
                    $reportSavedFileName = trim($reportSavedFileName, '.-');
                case "base":
                default:
                    $reportSavedFileName = sanitize_filename($reportSavedFileName,$lowercase,false,false);
                    $reportSavedFileName = mb_substr($reportSavedFileName,0,254,'UTF-8');
                    break;
            }
            $reportSavedFileName = $reportSavedFileName.'.pdf';
        }
        return $reportSavedFileName;
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
     * Get attachement content by email
     */
    private function _getEmailAttachements($sType)
    {
        /* @todo : search the for needed other replace (invite and remind) */
        switch ($sType) {
            case 'confirm':
                $sType = 'confirmation';
            default :
        }
        $aRelevantAttachments = array();
        $aSurvey=getSurveyInfo($this->_iSurveyId,Yii::app()->language);
        $aAttachments = unserialize($aSurvey['attachments']);
        /*
         * Iterate through attachments and check them for relevance.
         */
        if (!empty($aAttachments[$sType])) {
            foreach ($aAttachments[$sType] as $aAttachment) {
                // If the attachment is relevant it will be added to the mail.
                if (LimeExpressionManager::ProcessRelevance($aAttachment['relevance']) && @file_exists($aAttachment['url'])) {
                    $aRelevantAttachments[] = $aAttachment['url'];
                }
            }
        }
        return $aRelevantAttachments;
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
            /* @todo : get parent */
            return file_get_contents($oTemplate->filesPath.'/pdfreport.css');
        }
        return file_get_contents(dirname(__FILE__).'/pdfreport.css');
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
     * @param null|integer $questionNum the $qid of question being replaced - needed for properly alignment of question-level relevance and tailoring
     * @return string
     */
    private function _EMProcessString($string, $questionNum = null)
    {
        Yii::app()->setConfig('surveyID',$this->_iSurveyId);
        $oSurvey=Survey::model()->findByPk($this->_iSurveyId);
        $replacementFields=array(
            'SAVEDID'=>$this->_iResponseId,
            'SITENAME'=>App()->getConfig('sitename'),
            'SURVEYNAME'=>$oSurvey->getLocalizedTitle(),
            'SURVEYRESOURCESURL'=> Yii::app()->getConfig("uploadurl").'/surveys/'.$this->_iSurveyId.'/'
        );
        if(intval(Yii::app()->getConfig('versionnumber'))<3) {
            return \LimeExpressionManager::ProcessString($string, null, $replacementFields, false, 3, 0, false, false, true);
        }
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3.6.2","<")) {
            return \LimeExpressionManager::ProcessString($string, null, $replacementFields, 3, 0, false, false, true);
        }
        return \LimeExpressionManager::ProcessStepString($string, true, 3, $replacementFields);
    }

    /**
     * Transliterate
     * @param string
     * @return string
     */
    private function _transliterate($string)
    {
        if(class_exists("Transliterator")) {
            $myTrans = Transliterator::create('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove');
            return $myTrans->transliterate($string);
        }
        /* from https://github.com/WordPress/WordPress/blob/dc8e9c6de0df706fee45bc82ac26cbc4fbcb8a7f/wp-includes/formatting.php#L1596 */
        $chars = array(
            // Decompositions for Latin-1 Supplement
            'ª' => 'a',
            'º' => 'o',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 's',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',
            'Ø' => 'O',
            // Decompositions for Latin Extended-A
            'Ā' => 'A',
            'ā' => 'a',
            'Ă' => 'A',
            'ă' => 'a',
            'Ą' => 'A',
            'ą' => 'a',
            'Ć' => 'C',
            'ć' => 'c',
            'Ĉ' => 'C',
            'ĉ' => 'c',
            'Ċ' => 'C',
            'ċ' => 'c',
            'Č' => 'C',
            'č' => 'c',
            'Ď' => 'D',
            'ď' => 'd',
            'Đ' => 'D',
            'đ' => 'd',
            'Ē' => 'E',
            'ē' => 'e',
            'Ĕ' => 'E',
            'ĕ' => 'e',
            'Ė' => 'E',
            'ė' => 'e',
            'Ę' => 'E',
            'ę' => 'e',
            'Ě' => 'E',
            'ě' => 'e',
            'Ĝ' => 'G',
            'ĝ' => 'g',
            'Ğ' => 'G',
            'ğ' => 'g',
            'Ġ' => 'G',
            'ġ' => 'g',
            'Ģ' => 'G',
            'ģ' => 'g',
            'Ĥ' => 'H',
            'ĥ' => 'h',
            'Ħ' => 'H',
            'ħ' => 'h',
            'Ĩ' => 'I',
            'ĩ' => 'i',
            'Ī' => 'I',
            'ī' => 'i',
            'Ĭ' => 'I',
            'ĭ' => 'i',
            'Į' => 'I',
            'į' => 'i',
            'İ' => 'I',
            'ı' => 'i',
            'Ĳ' => 'IJ',
            'ĳ' => 'ij',
            'Ĵ' => 'J',
            'ĵ' => 'j',
            'Ķ' => 'K',
            'ķ' => 'k',
            'ĸ' => 'k',
            'Ĺ' => 'L',
            'ĺ' => 'l',
            'Ļ' => 'L',
            'ļ' => 'l',
            'Ľ' => 'L',
            'ľ' => 'l',
            'Ŀ' => 'L',
            'ŀ' => 'l',
            'Ł' => 'L',
            'ł' => 'l',
            'Ń' => 'N',
            'ń' => 'n',
            'Ņ' => 'N',
            'ņ' => 'n',
            'Ň' => 'N',
            'ň' => 'n',
            'ŉ' => 'n',
            'Ŋ' => 'N',
            'ŋ' => 'n',
            'Ō' => 'O',
            'ō' => 'o',
            'Ŏ' => 'O',
            'ŏ' => 'o',
            'Ő' => 'O',
            'ő' => 'o',
            'Œ' => 'OE',
            'œ' => 'oe',
            'Ŕ' => 'R',
            'ŕ' => 'r',
            'Ŗ' => 'R',
            'ŗ' => 'r',
            'Ř' => 'R',
            'ř' => 'r',
            'Ś' => 'S',
            'ś' => 's',
            'Ŝ' => 'S',
            'ŝ' => 's',
            'Ş' => 'S',
            'ş' => 's',
            'Š' => 'S',
            'š' => 's',
            'Ţ' => 'T',
            'ţ' => 't',
            'Ť' => 'T',
            'ť' => 't',
            'Ŧ' => 'T',
            'ŧ' => 't',
            'Ũ' => 'U',
            'ũ' => 'u',
            'Ū' => 'U',
            'ū' => 'u',
            'Ŭ' => 'U',
            'ŭ' => 'u',
            'Ů' => 'U',
            'ů' => 'u',
            'Ű' => 'U',
            'ű' => 'u',
            'Ų' => 'U',
            'ų' => 'u',
            'Ŵ' => 'W',
            'ŵ' => 'w',
            'Ŷ' => 'Y',
            'ŷ' => 'y',
            'Ÿ' => 'Y',
            'Ź' => 'Z',
            'ź' => 'z',
            'Ż' => 'Z',
            'ż' => 'z',
            'Ž' => 'Z',
            'ž' => 'z',
            'ſ' => 's',
            // Decompositions for Latin Extended-B
            'Ș' => 'S',
            'ș' => 's',
            'Ț' => 'T',
            'ț' => 't',
            // Euro Sign
            '€' => 'E',
            // GBP (Pound) Sign
            '£' => '',
            // Vowels with diacritic (Vietnamese)
            // unmarked
            'Ơ' => 'O',
            'ơ' => 'o',
            'Ư' => 'U',
            'ư' => 'u',
            // grave accent
            'Ầ' => 'A',
            'ầ' => 'a',
            'Ằ' => 'A',
            'ằ' => 'a',
            'Ề' => 'E',
            'ề' => 'e',
            'Ồ' => 'O',
            'ồ' => 'o',
            'Ờ' => 'O',
            'ờ' => 'o',
            'Ừ' => 'U',
            'ừ' => 'u',
            'Ỳ' => 'Y',
            'ỳ' => 'y',
            // hook
            'Ả' => 'A',
            'ả' => 'a',
            'Ẩ' => 'A',
            'ẩ' => 'a',
            'Ẳ' => 'A',
            'ẳ' => 'a',
            'Ẻ' => 'E',
            'ẻ' => 'e',
            'Ể' => 'E',
            'ể' => 'e',
            'Ỉ' => 'I',
            'ỉ' => 'i',
            'Ỏ' => 'O',
            'ỏ' => 'o',
            'Ổ' => 'O',
            'ổ' => 'o',
            'Ở' => 'O',
            'ở' => 'o',
            'Ủ' => 'U',
            'ủ' => 'u',
            'Ử' => 'U',
            'ử' => 'u',
            'Ỷ' => 'Y',
            'ỷ' => 'y',
            // tilde
            'Ẫ' => 'A',
            'ẫ' => 'a',
            'Ẵ' => 'A',
            'ẵ' => 'a',
            'Ẽ' => 'E',
            'ẽ' => 'e',
            'Ễ' => 'E',
            'ễ' => 'e',
            'Ỗ' => 'O',
            'ỗ' => 'o',
            'Ỡ' => 'O',
            'ỡ' => 'o',
            'Ữ' => 'U',
            'ữ' => 'u',
            'Ỹ' => 'Y',
            'ỹ' => 'y',
            // acute accent
            'Ấ' => 'A',
            'ấ' => 'a',
            'Ắ' => 'A',
            'ắ' => 'a',
            'Ế' => 'E',
            'ế' => 'e',
            'Ố' => 'O',
            'ố' => 'o',
            'Ớ' => 'O',
            'ớ' => 'o',
            'Ứ' => 'U',
            'ứ' => 'u',
            // dot below
            'Ạ' => 'A',
            'ạ' => 'a',
            'Ậ' => 'A',
            'ậ' => 'a',
            'Ặ' => 'A',
            'ặ' => 'a',
            'Ẹ' => 'E',
            'ẹ' => 'e',
            'Ệ' => 'E',
            'ệ' => 'e',
            'Ị' => 'I',
            'ị' => 'i',
            'Ọ' => 'O',
            'ọ' => 'o',
            'Ộ' => 'O',
            'ộ' => 'o',
            'Ợ' => 'O',
            'ợ' => 'o',
            'Ụ' => 'U',
            'ụ' => 'u',
            'Ự' => 'U',
            'ự' => 'u',
            'Ỵ' => 'Y',
            'ỵ' => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin)
            'ɑ' => 'a',
            // macron
            'Ǖ' => 'U',
            'ǖ' => 'u',
            // acute accent
            'Ǘ' => 'U',
            'ǘ' => 'u',
            // caron
            'Ǎ' => 'A',
            'ǎ' => 'a',
            'Ǐ' => 'I',
            'ǐ' => 'i',
            'Ǒ' => 'O',
            'ǒ' => 'o',
            'Ǔ' => 'U',
            'ǔ' => 'u',
            'Ǚ' => 'U',
            'ǚ' => 'u',
            // grave accent
            'Ǜ' => 'U',
            'ǜ' => 'u',
        );
        $chars['Ä'] = 'Ae';
        $chars['ä'] = 'ae';
        $chars['Ö'] = 'Oe';
        $chars['ö'] = 'oe';
        $chars['Ü'] = 'Ue';
        $chars['ü'] = 'ue';
        $chars['ß'] = 'ss';
        $chars['Æ'] = 'Ae';
        $chars['æ'] = 'ae';
        $chars['Ø'] = 'Oe';
        $chars['ø'] = 'oe';
        $chars['Å'] = 'Aa';
        $chars['å'] = 'aa';
        $chars['l·l'] = 'll';
        $chars['Đ'] = 'DJ';
        $chars['đ'] = 'dj';
        return strtr( $string, $chars );
    }
}
