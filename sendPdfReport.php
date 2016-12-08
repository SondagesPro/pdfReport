<?php
/**
 * sendPdfReport Plugin for LimeSurvey
 * Use question text to create a report and send it by email.
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015-2016 Denis Chenu <http://sondages.pro>
 * @copyright 2015 Ingeus <http://www.ingeus.fr/>
 * @license GPL v3
 * @version 1.2
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
    static protected $description = 'Send a PDF report to specific email (v1.1).';
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
    protected $settings = array(
        'filenameend'=>array(
            'type'=>'select',
            'label'=>'Use token (if exist) for file name.',
            'options'=>array(
                'token'=>'Yes',
                'responseid'=>'No',
            ),
            'default'=>'token',
        ),
        'name_confirm'=>array(
            'type'=>'string',
            'label'=>'Base file name for confirmation pdf file',
            'default'=>'confirm',
        ),
        'name_admin_notification'=>array(
            'type'=>'string',
            'label'=>'Base file name for admin_notification pdf file',
            'default'=>'admin_notification',
        ),
        'name_admin_responses'=>array(
            'type'=>'string',
            'label'=>'Base file name for admin_responses pdf file',
            'default'=>'admin_responses',
        ),
        'basesavedirectory'=>array(
            'type'=>'string',
            'label'=>'Directory on the server to move the file after send (set to empty to remove the file)',
            'default'=>'',
        ),

    );
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
        if(!empty($aLogo['url'])){
            $sLogoComplement="&nbsp;<img src='{$aLogo['url']}' alt='{$aLogo['url']}' style='max-width:8em;max-height:2em;' />";
        }elseif(!empty($aLogo['error'])){
            $sLogoComplement="&nbsp;<span class='label label-warning'>{$aLogo['error']}</span>";
        }else{
            $sLogoComplement="";
        }
        $event->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'pdf_info' => array(
                    'type'=> 'info',
                    'content'=>"<p class='alert alert-info'><strong>Attention</strong> : Don't use default mail sending by LimeSurvey and set Send confirmation emails? to NO if you use confirmation email.</p>",
                ),
                'pdf_global' => array(
                    'type'=> 'info',
                    'content'=>"<p class='label label-info'>".gt("Global settings")."</p>",
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
                    'content'=>"<p class='label label-info'>".gt("Confirmation email")."</p>",
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
                    'content'=>"<p class='label label-info'>".gt("Basic admin notification")."</p>",
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
                    'content'=>"<p class='label label-info'>".gt("Detailed admin notification")."</p>",
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
            if($this->get("pdf_{$sMailType}_to", 'Survey', $iSurveyId)){
                $aSettings[$sMailType]=array(
                    "to"=>$this->get("pdf_{$sMailType}_to", 'Survey', $iSurveyId),
                    "attachement"=>$this->get("pdf_{$sMailType}_attachment", 'Survey', $iSurveyId),
                );
            }
            else{
                $aSettings[$sMailType]=null;
            }
        }

        if(count($aSettings)==0){
            return;
        }
        if($iResponseId===null){
            return;
        }
        $oSessionSurvey=Yii::app()->session["survey_{$iSurveyId}"];
        if(!isset($oSessionSurvey['s_lang'])){
            return;
        }
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
                    Yii::log("Email with ".$sFile." can not be sent due to a mail error",'error','application.plugins.sendPdfReport');
                }
                else
                {
                    Yii::log("Email with ".$sFile." sent",'info','application.plugins.sendPdfReport');
                }
            }
            if($sFile)
            {
                if($sBaseDir=$this->get("basesavedirectory"))
                {
                    if(is_dir($sBaseDir) && is_writable($sBaseDir))
                    {
                        $sBaseDir=rtrim($sBaseDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                        if(rename($sFile,$sBaseDir.basename($sFile))){
                            Yii::log("File is now at ".$sBaseDir.basename($sFile),'info','application.plugins.sendPdfReport');
                        }else{
                            Yii::log("An error happen when try to move to $sBaseDir",'error','application.plugins.sendPdfReport');
                        }
                    }
                    else
                    {
                        Yii::log("Invalid directoty $sBaseDir",'error','application.plugins.sendPdfReport');
                        @unlink($sFile);
                    }
                }
                else{
                    @unlink($sFile);
                }
            }
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
        Yii::log("getPdfFile start for {$sType} in {$this->iSurveyId}",'trace','application.plugins.sendPdfReport');
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
        Yii::import("sendPdfReport.pdfReport");

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
                    )
            );
            $sText=$oPurifier->purify($sText);

        }
        $sText=str_replace($pdfReplaced, $pdfSpecific, $sText);
        $sText="<style>\n{$sCssContent}\n</style>\n$sText\n";
        //~ $this->event->getContent($this)
              //~ ->addContent(htmlentities($sText));
        $aLogo=$this->getLogoPaths();
        if(!empty($aLogo['path']))
           $oPDF->sLogoFile=$aLogo['path'];

        $oPDF->initAnswerPDF($aSurvey, $aPdfLanguageSettings, $sHeader, $sSubHeader);
        // output the HTML content
        $oPDF->writeHTML($sText, true, false, true, false, '');

        $oPDF->lastPage();
        $sFilePdfName=Yii::app()->getConfig("tempdir").DIRECTORY_SEPARATOR.$this->get("name_{$sType}",null,null,$this->settings["name_{$sType}"]["default"])."_{$this->iSurveyId}_";
        $oSessionSurvey=Yii::app()->session["survey_{$this->iSurveyId}"];
        if(!empty($oSessionSurvey['token']) && $this->get("filenameend",null,null,$this->settingd['filenameend']['default'])=='token')
        {
            $sFilePdfName.="{$oSessionSurvey['token']}.pdf";

        }
        else
        {
            $sFilePdfName.="{$this->iResponseId}.pdf";
        }
        $oPDF->Output($sFilePdfName, 'F');
        Yii::log("getPdfFile done for {$sType} in {$this->iSurveyId}",'trace','application.plugins.sendPdfReport');
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

    public function saveSettings($settings)
    {
        if(isset($settings['basesavedirectory']) && !empty($settings['basesavedirectory']))
        {
            if (!is_dir($settings['basesavedirectory']))
            {
                Yii::app()->setFlashMessage("Directory not found, base directory is set to none",'error');
                $settings['basesavedirectory']="";
            }
            elseif (!is_writable($settings['basesavedirectory']))
            {
                Yii::app()->setFlashMessage("Directory found but not writable, base directory is set to none",'error');
                $settings['basesavedirectory']="";
            }
        }
        $updated =false;
        if(isset($settings['name_confirm']))
        {
            $settings['name_confirm']=preg_replace('/[^a-z0-9-]/i', '', $settings['name_confirm']);
            if(empty($settings['name_confirm']))
            {
                $settings['name_confirm']=$this->settings['name_confirm']['default'];
                $updated=true;
            }
        }
        if(isset($settings['name_admin_notification']))
        {
            $settings['name_admin_notification']=preg_replace('/[^a-z0-9-]/i', '', $settings['name_admin_notification']);
            if(empty($settings['name_admin_notification']) || $settings['name_admin_notification']==$settings['name_confirm'])
            {
                $settings['name_admin_notification']=$this->settings['name_admin_notification']['default'];
                $updated=true;
            }
        }
        if(isset($settings['name_admin_responses']))
        {
            $settings['name_admin_responses']=preg_replace('/[^a-z0-9-]/i', '', $settings['name_admin_responses']);
            if(empty($settings['name_admin_responses']) || $settings['name_admin_responses']==$settings['name_confirm'] || $settings['name_admin_responses']==$settings['name_admin_notification'] )
            {
                $settings['name_admin_responses']=$this->settings['name_admin_responses']['default'];
                $updated=true;
            }
        }
        if($updated){
            Yii::app()->setFlashMessage("One or more of PDF file name was updated. Review the filenames.",'error');
        }

        parent::saveSettings($settings);
    }
}
