<?php
/**
 * sendPdfReport Plugin for LimeSurvey
 * Use question text to create a report and send it by email.
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015 Denis Chenu <http://sondages.pro>
 * @copyright 2015 Ingeus <http://www.ingeus.fr/>
 * @license GPL v3
 * @version 0.9
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
 
class sendPdfReport extends PluginBase {
    protected $storage = 'DbStorage';    
    static protected $description = 'Send a PDF report to specific email (v0.9).';
    static protected $name = 'sendPdfReport';

    private $iSurveyId;
    private $iResponseId;
    private $sLanguage;

    public function __construct(PluginManager $manager, $id) 
    {
        parent::__construct($manager, $id);
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('afterSurveyComplete', 'mailPdfReport');
    }

    /**
     * This event is fired by the administration panel to gather extra settings
     * available for a survey.
     * The plugin should return setting meta data.
     * @param PluginEvent $event
     */
    public function beforeSurveySettings()
    {
        $event = $this->event;
        $aLogo=$this->getLogoPaths();
        if(!empty($aLogo['url']))
            $sLogoComplement="&nbsp;<img src='{$aLogo['url']}' alt='logo' style='width:100px' />";
        elseif(!empty($aLogo['error']))
            $sLogoComplement="&nbsp;<span class='label label-warning'>{$aLogo['error']}</span>";
        else
            $sLogoComplement="";

        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'pdf_info' => array(
                    'type'=> 'info',
                    'content'=>"<p class='alert'><strong>Attention</strong> : Don't use default mail sending by LimeSurvey and set Send confirmation emails? to NO if you use confirmation email.</p>".
                    "<p  class='alert'>The PDF attached are the content of the question text choosen</p>",
                ),
                'pdf_global' => array(
                    'type'=> 'info',
                    'content'=>"<p class='alert alert-info'>".gt("Global settings")."</p>",
                ),
                'pdf_header' => array(
                    'type'=> 'string',
                    'label'=>gt("PDF title"),
                    'current'=>$this->get('pdf_header','Survey',$event->get('survey'),"{SITENAME}"),
                ),
                'pdf_subheader' => array(
                    'type'=> 'string',
                    'label'=>gt("PDF sub title"),
                    'current'=>$this->get('pdf_subheader','Survey',$event->get('survey'),"{SURVEYNAME}"),
                ),
                'pdf_logo' => array(
                    'type'=> 'string',
                    'label'=>gt("Logo Name").$sLogoComplement,
                    'current'=>$this->get('pdf_logo','Survey',$event->get('survey')),
                ),
                'pdf_confirm' => array(
                    'type'=> 'info',
                    'content'=>"<p class='alert alert-info'>".gt("Confirmation email")."</p>",
                ),
                'pdf_confirm_to' => array(
                    'type'=> 'string',
                    'label'=>gt("Send confirmation emails?"),
                    'current'=>$this->get('pdf_confirm_to','Survey',$event->get('survey')),
                ),
                'pdf_confirm_attachment' => array(
                    'type'=> 'string',
                    'label'=>gt("Confirmation attachments:")." (".gt("Question code").")",
                    'current'=>$this->get('pdf_confirm_attachment','Survey',$event->get('survey')),
                ),
                'pdf_admin_notification' => array(
                    'type'=> 'info',
                    'content'=>"<p class='alert alert-info'>".gt("Basic admin notification")."</p>",
                ),
                'pdf_admin_notification_to' => array(
                    'type'=> 'string',
                    'label'=>gt("Basic email notification is sent to:"),
                    'current'=>$this->get('pdf_admin_notification_to','Survey',$event->get('survey')),
                ),
                'pdf_admin_notification_attachment' => array(
                    'type'=> 'string',
                    'label'=>gt("Basic notification attachments:")." (".gt("Question code").")",
                    'current'=>$this->get('pdf_admin_notification_attachment','Survey',$event->get('survey')),
                ),
                'pdf_admin_responses' => array(
                    'type'=> 'info',
                    'content'=>"<p class='alert alert-info'>".gt("Detailed admin notification")."</p>",
                ),
                'pdf_admin_responses_to' => array(
                    'type'=> 'string',
                    'label'=>gt("Send detailed admin notification email to: "),
                    'current'=>$this->get('pdf_admin_responses_to','Survey',$event->get('survey')),
                ),
                'pdf_admin_responses_attachment' => array(
                    'type'=> 'string',
                    'label'=>gt("Detailed notification attachments:")." (".gt("Question code").")",
                    'current'=>$this->get('pdf_admin_responses_attachment','Survey',$event->get('survey')),
                ),
                
            )
        ));
    }
    public function newSurveySettings()
    {
        //~ parent::newSurveySettings();
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
        // Maybe fix Survey ?
    }
    /*
     * Below are the actual methods that handle events
     */
    public function mailPdfReport() 
    {
        $oEvent      = $this->getEvent();
        $this->iSurveyId=$iSurveyId   = $oEvent->get('surveyId');
        $this->iResponseId=$iResponseId = $oEvent->get('responseId');
        $aMailType=array("confirm","admin_notification","admin_responses");
        $oSurvey=Survey::model()->findByPk($iSurveyId);

        $aSettings=array();
        foreach($aMailType as $sMailType)
        {
            if($this->get("pdf_{$sMailType}_to", 'Survey', $iSurveyId))
            {
                $aSettings[$sMailType]=array(
                    "to"=>$this->get("pdf_{$sMailType}_to", 'Survey', $iSurveyId),
                    "attachement"=>$this->get("pdf_{$sMailType}_attachment", 'Survey', $iSurveyId),
                );
            }
            else
                $aSettings[$sMailType]=null;
        }
        //~ echo "<pre>";
        //~ print_r(Yii::app()->session["survey_{$iSurveyId}"],1);
        //~ echo "</pre>";
        //~ die();
        if(count($aSettings)==0)
            return;
        if($iResponseId===null)
            return;
        $oSessionSurvey=Yii::app()->session["survey_{$iSurveyId}"];
        if(!isset($oSessionSurvey['s_lang']))
            return;
        $this->sLanguage=$sLanguage=$oSessionSurvey['s_lang'];
        foreach($aSettings as $sType=>$aMailing)
        {
            //$oQuestion=Question::model()->find("sid=:sid and language=:language and title=:title",array(":sid"=>$iSurveyId,":language"=>$sLanguage,":title"=>$aMailing['attachement']));
            $aRecipient=explode(";", ReplaceFields($aMailing['to'],array(), true));
            $aValidRecipient=array();
            foreach($aRecipient as $sRecipient)
            {
                $sRecipient=trim($sRecipient);
                if(validateEmailAddress($sRecipient))
                {
                    $aValidRecipient[]=$sRecipient;
                }
            }
            $aMessage=$this->getEmailContent($sType);
            $aAttachments = array();
            if($sFile=$this->getPdfFile($sType))
            {
                $aAttachments[]=$sFile;

            }
            foreach ($aValidRecipient as $sRecipient)
            {
                if (!SendEmailMessage($aMessage['message'], $aMessage['subject'],$sRecipient,"{$oSurvey->admin} <{$oSurvey->adminemail}>" , Yii::app()->getConfig("sitename"), true, getBounceEmail($iSurveyId), $aAttachments))
                {
                    //~ $oEvent->getContent($this)
                          //~ ->addContent('Email could not be sent.');
                }
                else
                {
                    //~ $oEvent->getContent($this)
                          //~ ->addContent('Email sent.');
                }
            }
            if($sFile)
                unlink($sFile);
        }
    }
    private function getEmailContent($sType)
    {

        $thissurvey=$aSurvey=getSurveyInfo($this->iSurveyId,$this->sLanguage);
        $aReplacementVars=$this->getReplacementVars($sType=='confirm');
        
        //~ $sSubject=LimeExpressionManager::ProcessString($aSurvey["email_{$sType}_subj"], NULL, $aReplacementVars, false, 3, 0, false, false, true);
        //~ $sMessage=LimeExpressionManager::ProcessString($aSurvey["email_{$sType}"], NULL, $aReplacementVars, false, 3, 0, false, false, true);
        $aReData=array(
            'saved_id'=>$this->iResponseId,
            'thissurvey'=>getSurveyInfo($this->iSurveyId,$this->sLanguage),
        );
        $sSubject=templatereplace($aSurvey["email_{$sType}_subj"],$aReplacementVars,$aReData,'',false,null,array(),true);
        $sMessage=templatereplace($aSurvey["email_{$sType}"],$aReplacementVars,$aReData,'',false,null,array(),true);

        return array(
            'subject'=>$sSubject,
            'message'=>$sMessage,
        );
    }
    private function getReplacementVars($bWithToken=false)
    {
        static $aReplacementVars;
        if(!empty($aReplacementVars))
            return $aReplacementVars;
        $thissurvey=$aSurvey=getSurveyInfo($this->iSurveyId,$this->sLanguage);
        $aReplacementVars=array();
        $aReplacementVars['RELOADURL']='';
        $aReplacementVars['ADMINNAME'] = $aSurvey['adminname'];
        $aReplacementVars['ADMINEMAIL'] = $aSurvey['adminemail'];
        $aReplacementVars['VIEWRESPONSEURL']=Yii::app()->createAbsoluteUrl("/admin/responses/sa/view/surveyid/{$this->iSurveyId}/id/{$this->iResponseId}");
        $aReplacementVars['EDITRESPONSEURL']=Yii::app()->createAbsoluteUrl("/admin/dataentry/sa/editdata/subaction/edit/surveyid/{$this->iSurveyId}/id/{$this->iResponseId}");
        $aReplacementVars['STATISTICSURL']=Yii::app()->createAbsoluteUrl("/admin/statistics/sa/index/surveyid/{$this->iSurveyId}");
        // Always HTML
        if (true)
        {
            $aReplacementVars['VIEWRESPONSEURL']="<a href='{$aReplacementVars['VIEWRESPONSEURL']}'>{$aReplacementVars['VIEWRESPONSEURL']}</a>";
            $aReplacementVars['EDITRESPONSEURL']="<a href='{$aReplacementVars['EDITRESPONSEURL']}'>{$aReplacementVars['EDITRESPONSEURL']}</a>";
            $aReplacementVars['STATISTICSURL']="<a href='{$aReplacementVars['STATISTICSURL']}'>{$aReplacementVars['STATISTICSURL']}</a>";
        }
        $aReplacementVars['ANSWERTABLE']='';
        $oSessionSurvey=Yii::app()->session["survey_{$this->iSurveyId}"];
        if($bWithToken && !empty($oSessionSurvey['token']) && tableExists('{{tokens_' . $this->iSurveyId . '}}'))
        {
            $oToken=Token::model($this->iSurveyId)->find("token=:token",array('token' => $oSessionSurvey['token']));
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
    private function getPdfFile($sType)
    {
        $sQuestionCode=$this->get("pdf_{$sType}_attachment", 'Survey', $this->iSurveyId);
        if(empty($sQuestionCode))
            return;
        $oQuestion=Question::model()->find("sid=:sid AND title=:title AND language=:language",array(':sid'=>$this->iSurveyId,':title'=>$sQuestionCode,':language'=>$this->sLanguage));
        if(empty($oQuestion))
            return;
        if(!LimeExpressionManager::ProcessString("{".$oQuestion->title.".relevanceStatus}"))
            return ;
        $aReData=array(
            'saved_id'=>$this->iResponseId,
            'thissurvey'=>getSurveyInfo($this->iSurveyId,$this->sLanguage),
        );
        $sText=templatereplace($oQuestion->question,array(),$aReData,'',false,null,array(),true);
        $sHeader = $this->get("pdf_header", 'Survey', $this->iSurveyId,"{SITENAME}");
        $sHeader=templatereplace($sHeader,array(),$aReData,'',false,null,array(),true);
        $sSubHeader = $this->get("pdf_subheader", 'Survey', $this->iSurveyId,"{SURVEYNAME}");
        $sSubHeader=templatereplace($sSubHeader,array(),$aReData,'',false,null,array(),true);
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
                )
        );



        //~ return;
        $sCssContent=file_get_contents(dirname(__FILE__).'/base.css');
        $sHeader=strip_tags($oPurifier->purify($sHeader));
        $sSubHeader=strip_tags($oPurifier->purify($sSubHeader));

        $aSurvey=getSurveyInfo($this->iSurveyId,$this->sLanguage);
        $sSurveyName = $aSurvey['surveyls_title'];
        Yii::setPathOfAlias('sendPdfReport', dirname(__FILE__));
        //define('K_PATH_IMAGES', Yii::app()->getConfig("homedir").DIRECTORY_SEPARATOR);

        Yii::import('application.libraries.admin.pdf', true);
        Yii::import('application.helpers.pdfHelper');
        Yii::import("sendPdfReport.pdfReport");

        $aPdfLanguageSettings=pdfHelper::getPdfLanguageSettings($this->sLanguage);

        $oPDF = new pdfReport();
        if(false && function_exists ("tidy_parse_string")) // Call to undefined function tidy_parse_string() in ./application/third_party/tcpdf/include/tcpdf_static.php on line 2099
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
            // TODO : Find the good way to use pagebreak="true", verify i page is used in tcpdf
            // ALT : explode/implode
            $pdfSpecific=array('<br pagebreak="true" />','<br pagebreak="true"/>','<br pagebreak="true">','<page>','</page>');
            $pdfReplaced=array('<span>br pagebreak="true"</span>','<span>br pagebreak="true"</span>','<span>br pagebreak="true"</span>','<span>page</span>','<span>/page</span>');
            $sText=str_replace($pdfSpecific, $pdfReplaced, $sText);
            $sText=$oPurifier->purify($sText);
            $sText=str_replace($pdfReplaced, $pdfSpecific, $sText);
            $sText="<style>\n{$sCssContent}\n</style>\n$sText\n";
        }
        //~ $this->event->getContent($this)
              //~ ->addContent(htmlentities($sText));
        $aLogo=$this->getLogoPaths();
        if(!empty($aLogo['path']))
           $oPDF->sLogoFile=$aLogo['path'];

        $oPDF->initAnswerPDF($aSurvey, $aPdfLanguageSettings, $sHeader, $sSubHeader);
        // output the HTML content
        $oPDF->writeHTML($sText, true, false, true, false, '');

        $oPDF->lastPage();
        $sFilePdfName=Yii::app()->getConfig("tempdir")."/{$sType}_{$this->iSurveyId}_{$this->iResponseId}.pdf";
        $oPDF->Output($sFilePdfName, 'F');
        return $sFilePdfName;
    }
    private function getLogoPaths()
    {
        
        $iSurveyId=$this->event->get('survey');
        if(empty($iSurveyId))
            $iSurveyId=$this->event->get('surveyId');
        $sLogoName=$this->get('pdf_logo','Survey',$iSurveyId);

        if(empty($sLogoName))
            return "empty";
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
