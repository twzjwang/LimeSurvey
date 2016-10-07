<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*/
/**
* Condition  Controller
*
* This controller performs token actions
*
* @package        LimeSurvey
* @subpackage    Backend
*/
class conditionsaction extends Survey_Common_Action {

    /**
     * @var array
     */
    private $stringComparisonOperators;

    /**
     * @var array
     */
    private $nonStringComparisonOperators;

    /**
     * @var int
     */
    private $iSurveyID;

    /**
     * @var string
     */
    private $language;

    /**
     * Init some stuff
     */
    public function __construct($controller = null, $id = null)
    {
        parent::__construct($controller, $id);

        $this->stringComparisonOperators = array(
            "<"      => gT("Less than"),
            "<="     => gT("Less than or equal to"),
            "=="     => gT("Equals"),
            "!="     => gT("Not equal to"),
            ">="     => gT("Greater than or equal to"),
            ">"      => gT("Greater than"),
            "RX"     => gT("Regular expression"),
            "a<b"    => gT("Less than (Strings)"),
            "a<=b"   => gT("Less than or equal to (Strings)"),
            "a>=b"   => gT("Greater than or equal to (Strings)"),
            "a>b"    => gT("Greater than (Strings)")
        );

        $this->nonStringComparisonOperators = array(
            "<"  => gT("Less than"),
            "<=" => gT("Less than or equal to"),
            "==" => gT("equals"),
            "!=" => gT("Not equal to"),
            ">=" => gT("Greater than or equal to"),
            ">"  => gT("Greater than"),
            "RX" => gT("Regular expression")
        );
    }

    /**
     * @param string $subaction
     * @param int $iSurveyID
     * @param int $gid
     * @param int $qid
     * @return void
     */
    public function index($subaction, $iSurveyID=null, $gid=null, $qid=null)
    {
        $iSurveyID = sanitize_int($iSurveyID);
        $this->iSurveyID = $iSurveyID;
        $gid = sanitize_int($gid);
        $qid = sanitize_int($qid);
        $imageurl = Yii::app()->getConfig("adminimageurl");
        Yii::app()->loadHelper("database");

        $aData['sidemenu']['state'] = false;
        $surveyinfo = Survey::model()->findByPk($iSurveyID)->surveyinfo;
        $aData['title_bar']['title'] = $surveyinfo['surveyls_title']."(".gT("ID").":".$iSurveyID.")";
        $aData['questionbar']['closebutton']['url'] = 'admin/questions/sa/view/surveyid/'.$iSurveyID.'/gid/'.$gid.'/qid/'.$qid;  // Close button
        $aData['questionbar']['buttons']['conditions'] = TRUE;

        switch($subaction) {
            case 'editconditionsform':
                $aData['questionbar']['buttons']['condition']['edit'] = TRUE;
                break;

            case 'conditions':
                $aData['questionbar']['buttons']['condition']['conditions'] = TRUE;
                break;

            case 'copyconditionsform':
                $aData['questionbar']['buttons']['condition']['copyconditionsform'] = TRUE;
                break;

            default:
                $aData['questionbar']['buttons']['condition']['edit'] = TRUE;
                break;
        }

        if( !empty($_POST['subaction']) ) $subaction=Yii::app()->request->getPost('subaction');

        //BEGIN Sanitizing POSTed data
        if ( !isset($iSurveyID) ) { $iSurveyID = returnGlobal('sid'); }
        if ( !isset($qid) ) { $qid = returnGlobal('qid'); }
        if ( !isset($gid) ) { $gid = returnGlobal('gid'); }
        if ( !isset($p_scenario)) {$p_scenario=returnGlobal('scenario');}
        if ( !isset($p_cqid))
        {
            $p_cqid = returnGlobal('cqid');
            if ($p_cqid == '') $p_cqid=0; // we are not using another question as source of condition
        }

        if (!isset($p_cid)) { $p_cid=returnGlobal('cid'); }
        if (!isset($p_subaction)) { if (isset($_POST['subaction'])) $p_subaction=$_POST['subaction']; else $p_subaction=$subaction;}
        if (!isset($p_cquestions)) {$p_cquestions=returnGlobal('cquestions');}
        if (!isset($p_csrctoken)) {$p_csrctoken=returnGlobal('csrctoken');}
        if (!isset($p_prevquestionsgqa)) {$p_prevquestionsgqa=returnGlobal('prevQuestionSGQA');}

        if (isset($_POST['canswers']) && is_array($_POST['canswers'])) {
            foreach ($_POST['canswers'] as $key => $val) {
                $p_canswers[$key]= preg_replace("/[^_.a-zA-Z0-9]@/", "", $val);
            }
        }
        else {
            $p_canswers = null;
        }

        $method = $this->getMethod();

        if (isset($_POST['method'])) {
            if ( !in_array($_POST['method'], array_keys($method))) {
                $p_method = "==";
            }
            else {
                $p_method = trim ($_POST['method']);
            }
        }
        else {
            $p_method = null;
        }

        if (isset($_POST['newscenarionum'])) {
            $p_newscenarionum = sanitize_int($_POST['newscenarionum']);
        }
        else {
            $p_newscenarionum = null;
        }
        //END Sanitizing POSTed data

        $br = CHtml::openTag('br /');

        // Make sure that there is a sid
        if (!isset($iSurveyID) || !$iSurveyID) {
            Yii::app()->setFlashMessage(gT('You have not selected a survey'), 'error');
            $this->getController()->redirect(array('admin'));
        }

        // This will redirect after logic is reset
        if ($p_subaction == "resetsurveylogic") {
            $this->resetSurveyLogic($iSurveyID);
        }

        // Make sure that there is a qid
        if (!isset($qid) || !$qid) {
            Yii::app()->setFlashMessage(gT('You have not selected a question'), 'error');
            Yii::app()->getController()->redirect(Yii::app()->request->urlReferrer);
        }

        // If we made it this far, then lets develop the menu items
        // add the conditions container table
        $extraGetParams = "";
        if (isset($qid) && isset($gid)) {
            $extraGetParams = "/gid/{$gid}/qid/{$qid}";
        }

        $conditionsoutput_action_error = ""; // defined during the actions

        $markcidarray = Array();
        if (isset($_GET['markcid'])) {
            $markcidarray = explode("-", $_GET['markcid']);
        }

        // Begin process actions
        $args = array(
            'p_scenario'    => $p_scenario,
            'p_cquestions'  => $p_cquestions,
            'p_csrctoken'   => $p_csrctoken,
            'p_canswers'    => $p_canswers,
            'p_cqid'        => $p_cqid,
            'p_cid'         => $p_cid,
            'p_subaction'   => $p_subaction,
            'p_prevquestionsgqa' => $p_prevquestionsgqa,
            'p_newscenarionum' => $p_newscenarionum,
            'p_method'      => $p_method,
            'qid'           => $qid
        );

        // Subaction = form submission
        $this->applySubaction($p_subaction, $args);

        $cquestions = array();
        $canswers   = array();

        $language = Survey::model()->findByPk($iSurveyID)->language;
        $this->language = $language;

        //BEGIN: GATHER INFORMATION
        // 1: Get information for this question
        // @todo : use viewHelper::getFieldText and getFieldCode for 2.06 for string show to user
        $surveyIsAnonymized = $this->getSurveyIsAnonymized();

        list($questiontitle, $sCurrentFullQuestionText) = $this->getQuestionTitleAndText($qid);

        // 2: Get all other questions that occur before this question that are pre-determined answer types

        // To avoid natural sort order issues,
        // first get all questions in natural sort order
        // , and find out which number in that order this question is
        // Then, using the same array which is now properly sorted by group then question
        // Create an array of all the questions that appear AFTER the current one
        $questionRows = $this->getQuestionRows($qid);
        $questionlist = $this->getQuestionList($qid, $questionRows);
        $postquestionlist = $this->getPostQuestionList($qid, $questionRows);

        $theserows = $this->getTheseRows($questionlist);
        $postrows  = $this->getPostRows($postquestionlist);

        $questionscount=count($theserows);
        $postquestionscount=count($postrows);

        if (isset($postquestionscount) && $postquestionscount > 0)
        { //Build the array used for the questionNav and copyTo select boxes
            foreach ($postrows as $pr)
            {
                $pquestions[]  =array("text" => $pr['title'].": ".substr(strip_tags($pr['question']), 0, 80),
                "fieldname" => $pr['sid']."X".$pr['gid']."X".$pr['qid']);
            }
        }

        // Previous question parsing ==> building cquestions[] and canswers[]
        if ($questionscount > 0)
        {
            list($cquestions, $canswers) = $this->getCAnswersAndCQuestions($theserows);
        } //if questionscount > 0
        //END Gather Information for this question

        $args['sCurrentFullQuestionText'] = $sCurrentFullQuestionText;
        $args['questiontitle'] = $questiontitle;
        $args['gid'] = $gid;
        $questionNavOptions = $this->getQuestionNavOptions($theserows, $postrows, $args);

        //Now display the information and forms

        $javascriptpre = $this->getJavascriptForMatching($canswers, $cquestions, $surveyIsAnonymized);

        $aViewUrls = array();

        $oQuestion = Question::model()->find('qid=:qid', array(':qid'=>$qid));
        $aData['oQuestion']=$oQuestion;

        $aData['surveyid'] = $iSurveyID;
        $aData['qid'] = $qid;
        $aData['gid'] = $gid;
        $aData['imageurl'] = $imageurl;
        $aData['extraGetParams'] = $extraGetParams;
        $aData['questionNavOptions'] = $questionNavOptions;
        $aData['conditionsoutput_action_error'] = $conditionsoutput_action_error;
        $aData['javascriptpre'] = $javascriptpre;

        $aViewUrls['conditionshead_view'][] = $aData;

        //BEGIN DISPLAY CONDITIONS FOR THIS QUESTION
        if (
            $subaction == 'index' ||
            $subaction == 'editconditionsform' || $subaction == 'insertcondition' ||
            $subaction == "editthiscondition" || $subaction == "delete" ||
            $subaction == "updatecondition" || $subaction == "deletescenario" ||
            $subaction == "renumberscenarios" || $subaction == "deleteallconditions" ||
            $subaction == "updatescenario" ||
            $subaction == 'copyconditionsform' || $subaction == 'copyconditions' || $subaction == 'conditions'
        )
        {

            //3: Get other conditions currently set for this question
            $conditionscount = 0;
            $conditionsList=array();
            $s=0;
            $criteria=new CDbCriteria;
            $criteria->select='scenario';  // only select the 'scenario' column
            $criteria->condition='qid=:qid';
            $criteria->params=array(':qid'=>$qid);
            $criteria->order='scenario';
            $criteria->group='scenario';

            $scenarioresult = Condition::model()->findAll($criteria);
            $scenariocount=count($scenarioresult);

            $aData['conditionsoutput'] = '';
            $aData['extraGetParams'] = $extraGetParams;
            $aData['questionNavOptions'] = $questionNavOptions;
            $aData['conditionsoutput_action_error'] = $conditionsoutput_action_error;
            $aData['javascriptpre'] = $javascriptpre;
            $aData['onlyshow'] = sprintf(gT("Only show question %s IF"),$questiontitle .': '. $sCurrentFullQuestionText);
            $aData['sCurrentQuestionText'] = $questiontitle .': '.viewHelper::flatEllipsizeText($sCurrentFullQuestionText,true,'120');
            $aData['subaction'] = $subaction;
            $aData['scenariocount'] = $scenariocount;
            $aViewUrls['conditionslist_view'][] = $aData;

            if ($scenariocount > 0)
            {
                $this->registerScriptFile( 'ADMIN_SCRIPT_PATH', 'checkgroup.js');
                foreach ($scenarioresult as $scenarionr)
                {
                    $scenariotext = "";
                    if ($s == 0 && $scenariocount > 1)
                    {
                        $scenariotext = " -------- <i>Scenario {$scenarionr['scenario']}</i> --------";
                    }
                    if ($s > 0)
                    {
                        $scenariotext = " -------- <i>".gT("OR")." Scenario {$scenarionr['scenario']}</i> --------";
                    }
                    if ($subaction == "copyconditionsform" || $subaction == "copyconditions")
                    {
                        $initialCheckbox = "<td><input type='checkbox' id='scenarioCbx{$scenarionr['scenario']}' checked='checked'/>\n"
                        ."<script type='text/javascript'>$(document).ready(function () { $('#scenarioCbx{$scenarionr['scenario']}').checkgroup({ groupName:'aConditionFromScenario{$scenarionr['scenario']}'}); });</script>"
                        ."</td><td>&nbsp;</td>\n";
                    }
                    else
                    {
                        $initialCheckbox = "";
                    }

                    if (    $scenariotext != "" && ($subaction == "editconditionsform" || $subaction == "insertcondition" ||
                    $subaction == "updatecondition" || $subaction == "editthiscondition" ||
                    $subaction == "renumberscenarios" || $subaction == "updatescenario" ||
                    $subaction == "deletescenario" || $subaction == "delete")
                    )
                    {
                        $img_tag = '<span class="glyphicon glyphicon-trash"></span>';
                        $additional_main_content = CHtml::link($img_tag, '#', array(
                            'onclick'     =>     "if ( confirm('".gT("Are you sure you want to delete all conditions set in this scenario?", "js")."')) { document.getElementById('deletescenario{$scenarionr['scenario']}').submit();}"
                        ));

                        $img_tag = '<span class="glyphicon glyphicon-pencil"></span>';
                        $additional_main_content .= CHtml::link($img_tag, '#', array(
                        'id'         =>     'editscenariobtn'.$scenarionr['scenario'],
                        'onclick'     =>     "$('#editscenario{$scenarionr['scenario']}').toggle('slow');"
                        ));

                        $aData['additional_content'] = $additional_main_content;
                    }

                    $aData['initialCheckbox'] = $initialCheckbox;
                    $aData['scenariotext'] = $scenariotext;
                    $aData['scenarionr'] = $scenarionr;
                    if (!isset($aViewUrls['output'])) $aViewUrls['output']='';
                    $aViewUrls['output'] .= $this->getController()->renderPartial('/admin/conditions/includes/conditions_scenario',
                    $aData, TRUE);

                    unset($currentfield);

                    $conditionscount = Condition::model()->getConditionCount($qid, $this->language, $scenarionr);
                    $conditions = Condition::model()->getConditions($qid, $this->language, $scenarionr);
                    $conditionscounttoken = Condition::model()->getConditionCountToken($qid, $scenarionr);
                    $resulttoken = Condition::model()->getConditionsToken($qid, $scenarionr);

                    $conditionscount = $conditionscount + $conditionscounttoken;

                    ////////////////// BUILD CONDITIONS DISPLAY
                    if ($conditionscount > 0)
                    {
                        $aConditionsMerged=Array();
                        foreach ($resulttoken->readAll() as $arow)
                        {
                            $aConditionsMerged[]=$arow;
                        }
                        foreach ($conditions as $arow)
                        {
                            $aConditionsMerged[]=$arow;
                        }
                        foreach ($aConditionsMerged as $rows)
                        {
                            if($rows['method'] == "") {$rows['method'] = "==";} //Fill in the empty method from previous versions
                            $markcidstyle="oddrow";
                            if (array_search($rows['cid'], $markcidarray) !== FALSE){
                                // This is the style used when the condition editor is called
                                // in order to check which conditions prevent a question deletion
                                $markcidstyle="markedrow";
                            }
                            if ($subaction == "editthiscondition" && isset($p_cid) &&
                            $rows['cid'] === $p_cid)
                            {
                                // Style used when editing a condition
                                $markcidstyle="editedrow";
                            }

                            if (isset($currentfield) && $currentfield != $rows['cfieldname'] )
                            {
                                $aViewUrls['output'] .= gT("and");
                            }
                            elseif (isset($currentfield))
                            {
                                $aViewUrls['output'] .= gT("or");
                            }

                            $aViewUrls['output'] .= CHtml::form(array("/admin/conditions/sa/index/subaction/{$subaction}/surveyid/{$iSurveyID}/gid/{$gid}/qid/{$qid}/"), 'post', array('id'=>"conditionaction{$rows['cid']}",'name'=>"conditionaction{$rows['cid']}"))
                            ."<table class='table conditionstable'>\n"
                            ."\t<tr class='active'>\n";

                            if ( $subaction == "copyconditionsform" || $subaction == "copyconditions" )
                            {
                                $aViewUrls['output'] .= "<td>&nbsp;&nbsp;</td>"
                                . "<td class='scenariotd'>\n"
                                . "\t<input type='checkbox' name='aConditionFromScenario{$scenarionr['scenario']}' id='cbox{$rows['cid']}' value='{$rows['cid']}' checked='checked'/>\n"
                                . "</td>\n";
                            }
                            $aViewUrls['output'] .= ""
                            ."<td class='col-md-4 questionnamecol'>\n"
                            ."\t<span>\n";

                            $leftOperandType = 'unknown'; // prevquestion, tokenattr
                            if (!$surveyIsAnonymized && preg_match('/^{TOKEN:([^}]*)}$/',$rows['cfieldname'],$extractedTokenAttr) > 0)
                            {
                                $leftOperandType = 'tokenattr';
                                $aTokenAttrNames=getTokenFieldsAndNames($iSurveyID);
                                if(isset($aTokenAttrNames[strtolower($extractedTokenAttr[1])]))
                                {
                                    $thisAttrName=HTMLEscape($aTokenAttrNames[strtolower($extractedTokenAttr[1])]['description']);
                                }
                                else
                                {
                                    $thisAttrName=HTMLEscape($extractedTokenAttr[1]);
                                }
                                if(tableExists("{{tokens_$iSurveyID}}"))
                                {
                                    $thisAttrName.= " [".gT("From token table")."]";
                                }
                                else
                                {
                                    $thisAttrName.= " [".gT("Inexistant token table")."]";
                                }
                                $aViewUrls['output'] .= "\t$thisAttrName\n";
                                // TIBO not sure this is used anymore !!
                                $conditionsList[]=array("cid"=>$rows['cid'],
                                "text"=>$thisAttrName);
                            }
                            else
                            {
                                $leftOperandType = 'prevquestion';
                                foreach ($cquestions as $cqn)
                                {
                                    if ($cqn[3] == $rows['cfieldname'])
                                    {
                                        $aViewUrls['output'] .= "\t$cqn[0] (qid{$rows['cqid']})\n";
                                        $conditionsList[]=array("cid"=>$rows['cid'],
                                        "text"=>$cqn[0]." ({$rows['value']})");
                                    }
                                    else
                                    {
                                        //$aViewUrls['output'] .= "\t<font color='red'>ERROR: Delete this condition. It is out of order.</font>\n";
                                    }
                                }
                            }

                            $aViewUrls['output'] .= "\t</span></td>\n"
                            ."\t<td class='col-md-2 operatornametd'>\n"
                            ."<span>\n" //    .gT("Equals")."</font></td>"
                            .$method[trim ($rows['method'])]
                            ."</span>\n"
                            ."\t</td>\n"
                            ."\n"
                            ."\t<td class='col-md-3 questionanswertd'>\n"
                            ."<span>\n";

                            // let's read the condition's right operand
                            // determine its type and display it
                            $rightOperandType = 'unknown'; // predefinedAnsw,constantVal, prevQsgqa, tokenAttr, regexp
                            if ($rows['method'] == 'RX')
                            {
                                $rightOperandType = 'regexp';
                                $aViewUrls['output'] .= "".HTMLEscape($rows['value'])."\n";
                            }
                            elseif (preg_match('/^@([0-9]+X[0-9]+X[^@]*)@$/',$rows['value'],$matchedSGQA) > 0)
                            { // SGQA
                                $rightOperandType = 'prevQsgqa';
                                $textfound=false;
                                foreach ($cquestions as $cqn)
                                {
                                    if ($cqn[3] == $matchedSGQA[1])
                                    {
                                        $matchedSGQAText=$cqn[0];
                                        $textfound=true;
                                        break;
                                    }
                                }
                                if ($textfound === false)
                                {
                                    $matchedSGQAText=$rows['value'].' ('.gT("Not found").')';
                                }

                                $aViewUrls['output'] .= "".HTMLEscape($matchedSGQAText)."\n";
                            }
                            elseif (!$surveyIsAnonymized && preg_match('/^{TOKEN:([^}]*)}$/',$rows['value'],$extractedTokenAttr) > 0)
                            {
                                $rightOperandType = 'tokenAttr';
                                $aTokenAttrNames=getTokenFieldsAndNames($iSurveyID);
                                if (count($aTokenAttrNames) != 0)
                                {
                                    $thisAttrName=HTMLEscape($aTokenAttrNames[strtolower($extractedTokenAttr[1])]['description'])." [".gT("From token table")."]";
                                }
                                else
                                {
                                    $thisAttrName=HTMLEscape($extractedTokenAttr[1])." [".gT("Inexistant token table")."]";
                                }
                                $aViewUrls['output'] .= "\t$thisAttrName\n";
                            }
                            elseif (isset($canswers))
                            {
                                foreach ($canswers as $can)
                                {
                                    if ($can[0] == $rows['cfieldname'] && $can[1] == $rows['value'])
                                    {
                                        $aViewUrls['output'] .= "$can[2] ($can[1])\n";
                                        $rightOperandType = 'predefinedAnsw';

                                    }
                                }
                            }
                            // if $rightOperandType is still unknown then it is a simple constant
                            if ($rightOperandType == 'unknown')
                            {
                                $rightOperandType = 'constantVal';
                                if ($rows['value'] == ' ' ||
                                $rows['value'] == '')
                                {
                                    $aViewUrls['output'] .= "".gT("No answer")."\n";
                                }
                                else
                                {
                                    $aViewUrls['output'] .= "".HTMLEscape($rows['value'])."\n";
                                }
                            }

                            $aViewUrls['output'] .= "\t</span></td>\n"
                            ."\t<td class='text-right'>\n";

                            if ( $subaction == "editconditionsform" ||$subaction == "insertcondition" ||
                            $subaction == "updatecondition" || $subaction == "editthiscondition" ||
                            $subaction == "renumberscenarios" || $subaction == "deleteallconditions" ||
                            $subaction == "updatescenario" ||
                            $subaction == "deletescenario" || $subaction == "delete" )
                            { // show single condition action buttons in edit mode

                                $aData['rows'] = $rows;
                                $aData['sImageURL'] = Yii::app()->getConfig('adminimageurl');

                                //$aViewUrls['includes/conditions_edit'][] = $aData;

                                $aViewUrls['output'] .= $this->getController()->renderPartial('/admin/conditions/includes/conditions_edit',$aData, TRUE);

                                // now sets e corresponding hidden input field
                                // depending on the leftOperandType
                                if ($leftOperandType == 'tokenattr')
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('csrctoken', HTMLEscape($rows['cfieldname']), array(
                                    'id' => 'csrctoken'.$rows['cid']
                                    ));
                                }
                                else
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('cquestions', HTMLEscape($rows['cfieldname']),
                                    array(
                                    'id' => 'cquestions'.$rows['cid']
                                    )
                                    );
                                }

                                // now set the corresponding hidden input field
                                // depending on the rightOperandType
                                // This is used when editing a condition
                                if ($rightOperandType == 'predefinedAnsw')
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('EDITcanswers[]', HTMLEscape($rows['value']), array(
                                    'id' => 'editModeTargetVal'.$rows['cid']
                                    ));
                                }
                                elseif ($rightOperandType == 'prevQsgqa')
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('EDITprevQuestionSGQA', HTMLEscape($rows['value']),
                                    array(
                                    'id' => 'editModeTargetVal'.$rows['cid']
                                    ));
                                }
                                elseif ($rightOperandType == 'tokenAttr')
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('EDITtokenAttr', HTMLEscape($rows['value']), array(
                                    'id' => 'editModeTargetVal'.$rows['cid']
                                    ));
                                }
                                elseif ($rightOperandType == 'regexp')
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('EDITConditionRegexp', HTMLEscape($rows['value']),
                                    array(
                                    'id' => 'editModeTargetVal'.$rows['cid']
                                    ));
                                }
                                else
                                {
                                    $aViewUrls['output'] .= CHtml::hiddenField('EDITConditionConst', HTMLEscape($rows['value']),
                                    array(
                                    'id' => 'editModeTargetVal'.$rows['cid']
                                    ));
                                }
                            }

                            $aViewUrls['output']     .=     CHtml::closeTag('td')     . CHtml::closeTag('tr') .
                            CHtml::closeTag('table'). CHtml::closeTag('form');

                            $currentfield = $rows['cfieldname'];
                        }

                    }
                    $s++;
                }
                // If we have a condition, allways reset the condition, this can fix old import (see #09344)
                LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
            }
            else
            { // no condition ==> disable delete all conditions button, and display a simple comment
                // no_conditions
                $aViewUrls['output'] = $this->getController()->renderPartial('/admin/conditions/no_condition',$aData, true);
            }

            //// To close the div opened in condition header....  see : https://goo.gl/BY7gUJ
            $aViewUrls['afteroutput'] = '</div></div></div>';

        }
        //END DISPLAY CONDITIONS FOR THIS QUESTION

        //// NICE COMMENTS : but a subaction copy would be even nicer

        // BEGIN: DISPLAY THE COPY CONDITIONS FORM
        if ($subaction == "copyconditionsform"
            || $subaction == "copyconditions")
        {
            $aViewUrls['output'] .= $this->getCopyForm($qid, $gid, $conditionsList, $pquestions);
        }
        // END: DISPLAY THE COPY CONDITIONS FORM

        if ( isset($cquestions) )
        {
            if ( count($cquestions) > 0 && count($cquestions) <=10)
            {
                $qcount = count($cquestions);
            }
            else
            {
                $qcount = 9;
            }
        }
        else
        {
            $qcount = 0;
        }

        // Some extra args to getEditConditionForm
        $args['subaction'] = $subaction;
        $args['iSurveyID'] = $this->iSurveyID;
        $args['gid'] = $gid;
        $args['qcount'] = $qcount;
        $args['method'] = $method;
        $args['cquestions'] = $cquestions;
        $args['scenariocount'] = $scenariocount;

        if ($subaction == "editconditionsform"
            || $subaction == "insertcondition"
            || $subaction == "updatecondition"
            || $subaction == "deletescenario"
            || $subaction == "renumberscenarios"
            || $subaction == "deleteallconditions"
            || $subaction == "updatescenario"
            || $subaction == "editthiscondition"
            || $subaction == "delete"
        )
        {
            $aViewUrls['output'] .= $this->getEditConditionForm($args);
        }

        $conditionsoutput = $aViewUrls['output'];

        $aData['conditionsoutput'] = $conditionsoutput;
        $this->_renderWrappedTemplate('conditions', $aViewUrls, $aData);

        // TMSW Condition->Relevance:  Must call LEM->ConvertConditionsToRelevance() whenever Condition is added or updated - what is best location for that action?
    }

    /**
     * @param string $hinttext
     * @return string html
     * @todo Not used?
     */
    private function _showSpeaker($hinttext)
    {
        global $max;

        $imageurl = Yii::app()->getConfig("adminimageurl");

        if(!isset($max))
        {
            $max = 20;
        }
        $htmlhinttext=str_replace("'",'&#039;',$hinttext);  //the string is already HTML except for single quotes so we just replace these only
        $jshinttext=javascriptEscape($hinttext,true,true);

        if(strlen(html_entity_decode($hinttext,ENT_QUOTES,'UTF-8')) > ($max+3))
        {
            $shortstring = flattenText($hinttext,true);

            $shortstring = htmlspecialchars(mb_strcut(html_entity_decode($shortstring,ENT_QUOTES,'UTF-8'), 0, $max, 'UTF-8'));

            //output with hoover effect
            $reshtml= "<span style='cursor: hand' alt='".$htmlhinttext."' title='".$htmlhinttext."' "
            ." onclick=\"alert('".gT("Question","js").": $jshinttext')\" />"
            ." \"$shortstring...\" </span>"
            ."<span class='fa fa-commenting-o text-success' style='cursor: hand'  title='".$htmlhinttext."'></span>"
            ." onclick=\"alert('".gT("Question","js").": $jshinttext')\" />";
        }
        else
        {
            $shortstring = flattenText($hinttext,true);

            $reshtml= "<span title='".$shortstring."'> \"$shortstring\"</span>";
        }

        return $reshtml;

    }

    /**
     * This array will be used to explain wich conditions is used to evaluate the question
     * @return array
     */
    protected function getMethod()
    {
        if (Yii::app()->getConfig('stringcomparizonoperators') == 1) {
            $method = $this->stringComparisonOperators;
        }
        else {
            $method = $this->nonStringComparisonOperators;
        }

        return $method;
    }

    /**
     * @return void
     */
    protected function resetSurveyLogic($iSurveyID)
    {
        if (!isset($_GET['ok'])) {
            $data = array('iSurveyID' => $iSurveyID);
            $content = $this->getController()->renderPartial('/admin/conditions/deleteAllConditions', $data, true);
            $this->_renderWrappedTemplate('conditions', array('message' => array(
                'title' => gT("Warning"),
                'message' => $content
            )));
            Yii::app()->end();
        }
        else {
            LimeExpressionManager::RevertUpgradeConditionsToRelevance($iSurveyID);
            Condition::model()->deleteRecords("qid in (select qid from {{questions}} where sid={$iSurveyID})");
            Yii::app()->setFlashMessage(gT("All conditions in this survey have been deleted."));
            $this->getController()->redirect(array('admin/survey/sa/view/surveyid/'.$iSurveyID));
        }
    }

    /**
     * @todo Better way than to extract $args
     * @params $args
     * @return void
     */
    protected function insertCondition(array $args)
    {
        // Extract p_scenario, p_cquestions, ...
        extract($args);

        if (
            (
                !isset($p_canswers) &&
                !isset($_POST['ConditionConst']) &&
                !isset($_POST['prevQuestionSGQA']) &&
                !isset($_POST['tokenAttr']) &&
                !isset($_POST['ConditionRegexp'])
            ) ||
            (!isset($p_cquestions) && !isset($p_csrctoken))
        )
        {
            $conditionsoutput_action_error .= CHtml::script("\n<!--\n alert(\"".gT("Your condition could not be added! It did not include the question and/or answer upon which the condition was based. Please ensure you have selected a question and an answer.","js")."\")\n //-->\n");
        }
        else
        {
            if (isset($p_cquestions) && $p_cquestions != '')
            {
                $conditionCfieldname = $p_cquestions;
            }
            elseif(isset($p_csrctoken) && $p_csrctoken != '')
            {
                $conditionCfieldname = $p_csrctoken;
            }

            $condition_data = array(
                'qid'             => $qid,
                'scenario'         => $p_scenario,
                'cqid'             => $p_cqid,
                'cfieldname'     => $conditionCfieldname,
                'method'        => $p_method
            );

            if (isset($p_canswers))
            {
                foreach ($p_canswers as $ca)
                {
                    //First lets make sure there isn't already an exact replica of this condition
                    $condition_data['value'] = $ca;

                    $result = Condition::model()->findAllByAttributes($condition_data);

                    $count_caseinsensitivedupes = count($result);

                    if ($count_caseinsensitivedupes == 0)
                    {
                        $result = Condition::model()->insertRecords($condition_data);;
                    }
                }
            }

            unset($posted_condition_value);
            // Please note that autoUnescape is already applied in database.php included above
            // so we only need to db_quote _POST variables
            if (isset($_POST['ConditionConst']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#CONST")
            {
                $posted_condition_value = Yii::app()->request->getPost('ConditionConst');
            }
            elseif (isset($_POST['prevQuestionSGQA']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#PREVQUESTIONS")
            {
                $posted_condition_value = Yii::app()->request->getPost('prevQuestionSGQA');
            }
            elseif (isset($_POST['tokenAttr']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#TOKENATTRS")
            {
                $posted_condition_value = Yii::app()->request->getPost('tokenAttr');
            }
            elseif (isset($_POST['ConditionRegexp']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#REGEXP")
            {
                $posted_condition_value = Yii::app()->request->getPost('ConditionRegexp');
            }

            if (isset($posted_condition_value))
            {
                $condition_data['value'] = $posted_condition_value;
                $result = Condition::model()->insertRecords($condition_data);
            }
        }
        LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
    }

    /**
     * @param array $args
     * @return void
     */
    protected function updateCondition(array $args)
    {
        extract($args);

        if ((    !isset($p_canswers) &&
            !isset($_POST['ConditionConst']) &&
            !isset($_POST['prevQuestionSGQA']) &&
            !isset($_POST['tokenAttr']) &&
            !isset($_POST['ConditionRegexp'])) ||
            (!isset($p_cquestions) && !isset($p_csrctoken))
        )
        {
            $conditionsoutput_action_error .= CHtml::script("\n<!--\n alert(\"".gT("Your condition could not be added! It did not include the question and/or answer upon which the condition was based. Please ensure you have selected a question and an answer.","js")."\")\n //-->\n");
        }
        else
        {
            if ( isset($p_cquestions) && $p_cquestions != '' )
            {
                $conditionCfieldname = $p_cquestions;
            }
            elseif(isset($p_csrctoken) && $p_csrctoken != '')
            {
                $conditionCfieldname = $p_csrctoken;
            }

            if ( isset($p_canswers) )
            {
                foreach ($p_canswers as $ca)
                {
                    // This is an Edit, there will only be ONE VALUE
                    $updated_data = array(
                        'qid' => $qid,
                        'scenario' => $p_scenario,
                        'cqid' => $p_cqid,
                        'cfieldname' => $conditionCfieldname,
                        'method' => $p_method,
                        'value' => $ca
                    );
                    $result = Condition::model()->insertRecords($updated_data, TRUE, array('cid'=>$p_cid));
                }
            }

            unset($posted_condition_value);
            // Please note that autoUnescape is already applied in database.php included above
            // so we only need to db_quote _POST variables
            if (isset($_POST['ConditionConst']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#CONST")
            {
                $posted_condition_value = Yii::app()->request->getPost('ConditionConst');
            }
            elseif (isset($_POST['prevQuestionSGQA']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#PREVQUESTIONS")
            {
                $posted_condition_value = Yii::app()->request->getPost('prevQuestionSGQA');
            }
            elseif (isset($_POST['tokenAttr']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#TOKENATTRS")
            {
                $posted_condition_value = Yii::app()->request->getPost('tokenAttr');
            }
            elseif (isset($_POST['ConditionRegexp']) && isset($_POST['editTargetTab']) && $_POST['editTargetTab']=="#REGEXP")
            {
                $posted_condition_value = Yii::app()->request->getPost('ConditionRegexp');
            }

            if (isset($posted_condition_value))
            {
                $updated_data = array(
                    'qid' => $qid,
                    'scenario' => $p_scenario,
                    'cqid' => $p_cqid,
                    'cfieldname' => $conditionCfieldname,
                    'method' => $p_method,
                    'value' => $posted_condition_value
                );
                $result = Condition::model()->insertRecords($updated_data, TRUE, array('cid'=>$p_cid));
            }
        }
        LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
    }

    /**
     * @param array $args
     * @return void
     */
    protected function renumberScenarios(array $args)
    {
        extract($args);

        $query = "SELECT DISTINCT scenario FROM {{conditions}} WHERE qid=:qid ORDER BY scenario";
        $result = Yii::app()->db->createCommand($query)->bindParam(":qid", $qid, PDO::PARAM_INT)->query() or safeDie ("Couldn't select scenario<br />$query<br />");
        $newindex = 1;

        foreach ($result->readAll() as $srow)
        {
            // new var $update_result == old var $result2
            $update_result = Condition::model()->insertRecords(array('scenario'=>$newindex), TRUE,
                array( 'qid'=>$qid, 'scenario'=>$srow['scenario'] )
            );
            $newindex++;
        }
        LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
        Yii::app()->setFlashMessage(gT("All conditions scenarios were renumbered."));
    }

    /**
     * @param array $args
     * @return void
     */
    protected function copyConditions(array $args)
    {
        extract($args);

        $qid = returnGlobal('qid');
        $copyconditionsfrom = returnGlobal('copyconditionsfrom');
        $copyconditionsto = returnGlobal('copyconditionsto');
        if (isset($copyconditionsto) && is_array($copyconditionsto) && isset($copyconditionsfrom) && is_array($copyconditionsfrom)) {
            //Get the conditions we are going to copy
            foreach($copyconditionsfrom as &$entry)
                $entry = Yii::app()->db->quoteValue($entry);
            $query = "SELECT * FROM {{conditions}}\n"
                ."WHERE cid in (";
            $query .= implode(", ", $copyconditionsfrom);
            $query .= ")";
            $result = Yii::app()->db->createCommand($query)->query() or
                safeDie("Couldn't get conditions for copy<br />$query<br />");

            foreach ($result->readAll() as $row) {
                $proformaconditions[] = array(
                    "scenario"        =>    $row['scenario'],
                    "cqid"            =>    $row['cqid'],
                    "cfieldname"    =>    $row['cfieldname'],
                    "method"        =>    $row['method'],
                    "value"            =>    $row['value']
                );
            } // while

            foreach ($copyconditionsto as $copyc) {
                list($newsid, $newgid, $newqid)=explode("X", $copyc);
                foreach ($proformaconditions as $pfc) { //TIBO

                    //First lets make sure there isn't already an exact replica of this condition
                    $conditions_data = array(
                        'qid'             =>     $newqid,
                        'scenario'         =>     $pfc['scenario'],
                        'cqid'             =>     $pfc['cqid'],
                        'cfieldname'     =>     $pfc['cfieldname'],
                        'method'         =>    $pfc['method'],
                        'value'         =>     $pfc['value']
                    );

                    $result = Condition::model()->findAllByAttributes($conditions_data);

                    $count_caseinsensitivedupes = count($result);

                    $countduplicates = 0;
                    if ($count_caseinsensitivedupes != 0) {
                        foreach ($result as $ccrow) {
                            if ($ccrow['value'] == $pfc['value']) {
                                $countduplicates++;
                            }
                        }
                    }

                    if ($countduplicates == 0) { //If there is no match, add the condition.
                        $result = Condition::model()->insertRecords($conditions_data);
                        $conditionCopied = true;
                    }
                    else {
                        $conditionDuplicated = true;
                    }
                }
            }

            if (isset($conditionCopied) && $conditionCopied === true) {
                if (isset($conditionDuplicated) && $conditionDuplicated ==true) {
                    Yii::app()->setFlashMessage(gT("Condition successfully copied (some were skipped because they were duplicates)"), 'warning');
                }
                else {
                    Yii::app()->setFlashMessage(gT("Condition successfully copied"));
                }
            }
            else {
                Yii::app()->setFlashMessage(gT("No conditions could be copied (due to duplicates)"), 'error');
            }
        }
        LimeExpressionManager::UpgradeConditionsToRelevance($this->iSurveyID); // do for whole survey, since don't know which questions affected.
    }

    /**
     * Switch on action to update/copy/add condition etc
     * @param string $p_subaction
     * @param array $args
     * @return void
     */
    protected function applySubaction($p_subaction, array $args)
    {
        extract($args);
        switch ($p_subaction) {
            // Insert new condition
            case "insertcondition":
                $this->insertCondition($args);
                break;

            // Update entry if this is an edit
            case "updatecondition":
                $this->updateCondition($args);
                break;

            // Delete entry if this is delete
            case "delete":
                LimeExpressionManager::RevertUpgradeConditionsToRelevance(NULL,$qid);   // in case deleted the last condition
                $result = Condition::model()->deleteRecords(array('cid'=>$p_cid));
                LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
                break;

            // Delete all conditions in this scenario
            case "deletescenario":
                LimeExpressionManager::RevertUpgradeConditionsToRelevance(NULL,$qid);   // in case deleted the last condition
                $result = Condition::model()->deleteRecords(array('qid'=>$qid, 'scenario'=>$p_scenario));
                LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
                break;

            // Update scenario
            case "updatescenario":
                // TODO: Check if $p_newscenarionum is null
                $result = Condition::model()->insertRecords(array('scenario'=>$p_newscenarionum), TRUE, array(
                    'qid'=>$qid, 'scenario'=>$p_scenario));
                LimeExpressionManager::UpgradeConditionsToRelevance(NULL,$qid);
                break;

            // Delete all conditions for this question
            case "deleteallconditions":
                LimeExpressionManager::RevertUpgradeConditionsToRelevance(NULL,$qid);   // in case deleted the last condition
                $result = Condition::model()->deleteRecords(array('qid'=>$qid));
                break;

            // Renumber scenarios
            case "renumberscenarios":
                $this->renumberScenarios($args);
                break;

            // Copy conditions if this is copy
            case "copyconditions" :
                $this->copyConditions($args);
                break;
        }
    }

    /**
     * Renders template(s) wrapped in header and footer
     *
     * @param string $sAction Current action, the folder to fetch views from
     * @param string|array $aViewUrls View url(s)
     * @param array $aData Data to be passed on. Optional.
     * @return void
     */
    protected function _renderWrappedTemplate($sAction = 'conditions', $aViewUrls = array(), $aData = array())
    {
        ////$aData['display']['menu_bars'] = false;
        parent::_renderWrappedTemplate($sAction, $aViewUrls, $aData);
    }

    /**
     * @param array $questionlist
     * @return array
     */
    protected function getTheseRows(array $questionlist)
    {
        $theserows = array();
        foreach ($questionlist as $ql) {

            $result = Question::model()->with(array(
                'groups' => array(
                    'condition' => 'groups.language = :lang',
                    'params' => array(':lang' => $this->language)
                ),
            ))->findAllByAttributes(array('qid' => $ql, 'parent_qid' => 0, 'sid' => $this->iSurveyID, 'language' => $this->language));

            // And store again these questions in this array...
            foreach ($result as $myrows) {                   //key => value
                $theserows[] = array(
                    "qid"        =>    $myrows['qid'],
                    "sid"        =>    $myrows['sid'],
                    "gid"        =>    $myrows['gid'],
                    "question"    =>    $myrows['question'],
                    "type"        =>    $myrows['type'],
                    "mandatory"    =>    $myrows['mandatory'],
                    "other"        =>    $myrows['other'],
                    "title"        =>    $myrows['title']
                );
            }
        }
        return $theserows;
    }

    /**
     * @param array $postquestionlist
     * @return array
     */
    protected function getPostRows(array $postquestionlist)
    {
        $postrows = array();
        foreach ($postquestionlist as $pq) {
            $result = Question::model()->with(array(
                'groups' => array(
                    'condition' => 'groups.language = :lang',
                    'params' => array(':lang' => $this->language)
                ),
            ))->findAllByAttributes(array('qid' => $pq, 'parent_qid' => 0, 'sid' => $this->iSurveyID, 'language' => $this->language));

            foreach ($result as $myrows) {
                $postrows[] = array(
                    "qid"        =>    $myrows['qid'],
                    "sid"        =>    $myrows['sid'],
                    "gid"        =>    $myrows['gid'],
                    "question"    =>    $myrows['question'],
                    "type"        =>    $myrows['type'],
                    "mandatory"    =>    $myrows['mandatory'],
                    "other"        =>    $myrows['other'],
                    "title"        =>    $myrows['title']
                );
            }
        }
        return $postrows;
    }

    /**
     * @param int $qid
     * @return array (title, question text)
     */
    protected function getQuestionTitleAndText($qid)
    {
        $question = Question::model()->with('groups')->findByAttributes(array(
            'qid' => $qid,
            'parent_qid' => 0,
            'language' => $this->language
        ));
        return array($question['title'], $question['question']);
    }

    /**
     * @return boolean True if anonymized == 'Y' for this survey
     */
    protected function getSurveyIsAnonymized()
    {
        $info = getSurveyInfo($this->iSurveyID);
        return $info['anonymized'] == 'Y';
    }

    /**
     * @param int $qid
     * @return array
     */
    protected function getQuestionRows($qid)
    {
        $qresult = Question::model()->with(array(
            'groups' => array(
            'condition' => 'groups.language = :lang',
            'params' => array(':lang' => $this->language)
        )))->findAllByAttributes(array(
            'parent_qid' => 0,
            'sid' => $this->iSurveyID,
            'language' => $this->language)
        );

        $qrows = array();
        foreach ($qresult as $k => $v) {
            $qrows[$k] = array_merge($v->attributes, $v->groups->attributes);
        }

        // Perform a case insensitive natural sort on group name then question title (known as "code" in the form) of a multidimensional array
        usort($qrows, 'groupOrderThenQuestionOrder');

        return $qrows;
    }

    /**
     * @param int $qid
     * @param array $qrows
     * @return array
     */
    protected function getQuestionList($qid, array $qrows)
    {
        $position="before";
        $questionlist = array();
        // Go through each question until we reach the current one
        foreach ($qrows as $qrow)
        {
            if ($qrow["qid"] != $qid && $position=="before")
            {
                // remember all previous questions
                // all question types are supported.
                $questionlist[]=$qrow["qid"];
            }
            elseif ($qrow["qid"] == $qid)
            {
                break;
            }
        }
        return $questionlist;
    }

    /**
     * @param int $qid
     * @param array $qrows
     * @return array
     */
    protected function getPostQuestionList($qid, array $qrows)
    {
        $position = "before";
        $postquestionlist = array();
        foreach ($qrows as $qrow) //Go through each question until we reach the current one
        {
            if ( $qrow["qid"] == $qid )
            {
                $position = "after";
                //break;
            }
            elseif ($qrow["qid"] != $qid && $position=="after")
            {
                $postquestionlist[] = $qrow['qid'];
            }
        }
        return $postquestionlist;
    }

    /**
     * @param array $theserows
     * @return array (cquestion, canswers)
     */
    protected function getCAnswersAndCQuestions(array $theserows)
    {
        $X = "X";
        $cquestions = array();
        $canswers = array();

        foreach($theserows as $rows)
        {
            $shortquestion=$rows['title'].": ".strip_tags($rows['question']);

            if ($rows['type'] == "A" ||
                $rows['type'] == "B" ||
                $rows['type'] == "C" ||
                $rows['type'] == "E" ||
                $rows['type'] == "F" ||
                $rows['type'] == "H"
            )
            {
                $aresult = Question::model()->findAllByAttributes(array('parent_qid'=>$rows['qid'], 'language' => $this->language), array('order' => 'question_order ASC'));

                foreach ($aresult as $arows)
                {
                    $shortanswer = "{$arows['title']}: [" . flattenText($arows['question']) . "]";
                    $shortquestion = $rows['title'].":$shortanswer ".flattenText($rows['question']);
                    $cquestions[] = array( $shortquestion, $rows['qid'], $rows['type'],
                        $rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']
                    );

                    switch ($rows['type'])
                    {
                    case "A": //Array 5 buttons
                        for ($i=1; $i<=5; $i++)
                        {
                            $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], $i, $i);
                        }
                        break;
                    case "B": //Array 10 buttons
                        for ($i=1; $i<=10; $i++)
                        {
                            $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], $i, $i);
                        }
                        break;
                    case "C": //Array Y/N/NA
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "Y", gT("Yes"));
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "U", gT("Uncertain"));
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "N", gT("No"));
                        break;
                    case "E": //Array >/=/<
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "I", gT("Increase"));
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "S", gT("Same"));
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "D", gT("Decrease"));
                        break;
                    case "F": //Array Flexible Row
                    case "H": //Array Flexible Column

                        $fresult = Answer::model()->findAllByAttributes(array(
                            'qid' => $rows['qid'],
                            "language" => $this->language,
                            'scale_id' => 0,
                        ), array('order' => 'sortorder, code'));

                        foreach ($fresult as $frow)
                        {
                            $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], $frow['code'], $frow['answer']);
                        }
                        break;
                    }
                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "", gT("No answer"));
                    }

                } //while
            }
            elseif ($rows['type'] == ":" || $rows['type'] == ";")
            { // Multiflexi

                // Get the Y-Axis

                $fquery = "SELECT sq.*, q.other"
                    ." FROM {{questions sq}}, {{questions q}}"
                    ." WHERE sq.sid={$this->iSurveyID} AND sq.parent_qid=q.qid "
                    . "AND q.language=:lang1"
                    ." AND sq.language=:lang2"
                    ." AND q.qid=:qid
                    AND sq.scale_id=0
                    ORDER BY sq.question_order";
                    $sLanguage=$this->language;
                    $y_axis_db = Yii::app()->db->createCommand($fquery)
                        ->bindParam(":lang1", $sLanguage, PDO::PARAM_STR)
                        ->bindParam(":lang2", $sLanguage, PDO::PARAM_STR)
                        ->bindParam(":qid", $rows['qid'], PDO::PARAM_INT)
                        ->query();

                    // Get the X-Axis
                    $aquery = "SELECT sq.*
                        FROM {{questions q}}, {{questions sq}}
                        WHERE q.sid={$this->iSurveyID}
                        AND sq.parent_qid=q.qid
                        AND q.language=:lang1
                        AND sq.language=:lang2
                        AND q.qid=:qid
                        AND sq.scale_id=1
                        ORDER BY sq.question_order";

                    $x_axis_db=Yii::app()->db->createCommand($aquery)
                        ->bindParam(":lang1", $sLanguage, PDO::PARAM_STR)
                        ->bindParam(":lang2", $sLanguage, PDO::PARAM_STR)
                        ->bindParam(":qid", $rows['qid'], PDO::PARAM_INT)
                        ->query() or safeDie ("Couldn't get answers to Array questions<br />$aquery<br />");

                    foreach ($x_axis_db->readAll() as $frow)
                    {
                        $x_axis[$frow['title']]=$frow['question'];
                    }

                    foreach ($y_axis_db->readAll() as $yrow)
                    {
                        foreach($x_axis as $key=>$val)
                        {
                            $shortquestion=$rows['title'].":{$yrow['title']}:$key: [".strip_tags($yrow['question']). "][" .strip_tags($val). "] " . flattenText($rows['question']);
                            $cquestions[]=array($shortquestion, $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$yrow['title']."_".$key);
                            if ($rows['mandatory'] != 'Y')
                            {
                                $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$yrow['title']."_".$key, "", gT("No answer"));
                            }
                        }
                    }
                    unset($x_axis);
            } //if A,B,C,E,F,H
            elseif ($rows['type'] == "1") //Multi Scale
            {
                $aresult = Question::model()->findAllByAttributes(array('parent_qid' => $rows['qid'], 'language' => $this->language),
                array('order' => 'question_order desc'));

                foreach ($aresult as $arows)
                {
                    $attr = getQuestionAttributeValues($rows['qid']);
                    $sLanguage=$this->language;
                    // dualscale_header are allways set, but can be empty
                    $label1 = empty($attr['dualscale_headerA'][$sLanguage]) ? gT('Scale 1') : $attr['dualscale_headerA'][$sLanguage];
                    $label2 = empty($attr['dualscale_headerB'][$sLanguage]) ? gT('Scale 2') : $attr['dualscale_headerB'][$sLanguage];
                    $shortanswer = "{$arows['title']}: [" . strip_tags($arows['question']) . "][$label1]";
                    $shortquestion = $rows['title'].":$shortanswer ".strip_tags($rows['question']);
                    $cquestions[] = array($shortquestion, $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']."#0");

                    $shortanswer = "{$arows['title']}: [" . strip_tags($arows['question']) . "][$label2]";
                    $shortquestion = $rows['title'].":$shortanswer ".strip_tags($rows['question']);
                    $cquestions[] = array($shortquestion, $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']."#1");

                    // first label
                    $lresult = Answer::model()->findAllByAttributes(array('qid' => $rows['qid'], 'scale_id' => 0, 'language' => $this->language), array('order' => 'sortorder, answer'));
                    foreach ($lresult as $lrows)
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']."#0", "{$lrows['code']}", "{$lrows['code']}");
                    }

                    // second label
                    $lresult = Answer::model()->findAllByAttributes(array(
                        'qid' => $rows['qid'],
                        'scale_id' => 1,
                        'language' => $this->language,
                    ), array('order' => 'sortorder, answer'));

                    foreach ($lresult as $lrows)
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']."#1", "{$lrows['code']}", "{$lrows['code']}");
                    }

                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']."#0", "", gT("No answer"));
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']."#1", "", gT("No answer"));
                    }
                } //while
            }
            elseif ($rows['type'] == "K" ||$rows['type'] == "Q") //Multi shorttext/numerical
            {
                $aresult = Question::model()->findAllByAttributes(array(
                    "parent_qid" => $rows['qid'],
                    "language" =>$this->language,
                ), array('order' => 'question_order desc'));

                foreach ($aresult as $arows)
                {
                    $shortanswer = "{$arows['title']}: [" . strip_tags($arows['question']) . "]";
                    $shortquestion=$rows['title'].":$shortanswer ".strip_tags($rows['question']);
                    $cquestions[]=array($shortquestion, $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']);

                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], "", gT("No answer"));
                    }

                } //while
            }
            elseif ($rows['type'] == "R") //Answer Ranking
            {
                $aresult = Answer::model()->findAllByAttributes(array(
                    "qid" => $rows['qid'],
                    "scale_id" => 0,
                    "language" => $this->language,
                ), array('order' => 'sortorder, answer'));

                $acount = count($aresult);
                foreach ($aresult as $arow)
                {
                    $theanswer = addcslashes($arow['answer'], "'");
                    $quicky[]=array($arow['code'], $theanswer);
                }
                for ($i=1; $i<=$acount; $i++)
                {
                    $cquestions[]=array("{$rows['title']}: [RANK $i] ".strip_tags($rows['question']), $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$i);
                    foreach ($quicky as $qck)
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$i, $qck[0], $qck[1]);
                    }
                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$i, " ", gT("No answer"));
                    }
                }
                unset($quicky);
            } // End if type R
            elseif($rows['type'] == "M" || $rows['type'] == "P")
            {
                $shortanswer = " [".gT("Group of checkboxes")."]";
                $shortquestion = $rows['title'].":$shortanswer ".strip_tags($rows['question']);
                $cquestions[] = array($shortquestion, $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid']);

                $aresult = Question::model()->findAllByAttributes(array(
                    "parent_qid" => $rows['qid'],
                    "language" => $this->language
                ), array('order' => 'question_order desc'));

                foreach ($aresult as $arows)
                {
                    $theanswer = addcslashes($arows['question'], "'");
                    $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], $arows['title'], $theanswer);

                    $shortanswer = "{$arows['title']}: [" . strip_tags($arows['question']) . "]";
                    $shortanswer .= "[".gT("Single checkbox")."]";
                    $shortquestion=$rows['title'].":$shortanswer ".strip_tags($rows['question']);
                    $cquestions[]=array($shortquestion, $rows['qid'], $rows['type'], "+".$rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title']);
                    $canswers[]=array("+".$rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], 'Y', gT("checked"));
                    $canswers[]=array("+".$rows['sid'].$X.$rows['gid'].$X.$rows['qid'].$arows['title'], '', gT("not checked"));
                }
            }
            elseif($rows['type'] == "X") //Boilerplate question
            {
                //Just ignore this questiontype
            }
            else
            {
                $cquestions[]=array($shortquestion, $rows['qid'], $rows['type'], $rows['sid'].$X.$rows['gid'].$X.$rows['qid']);
                switch ($rows['type'])
                {
                case "Y": // Y/N/NA
                    $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], "Y", gT("Yes"));
                    $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], "N", gT("No"));
                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], " ", gT("No answer"));
                    }
                    break;
                case "G": //Gender
                    $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], "F", gT("Female"));
                    $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], "M", gT("Male"));
                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], " ", gT("No answer"));
                    }
                    break;
                case "5": // 5 choice
                    for ($i=1; $i<=5; $i++)
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], $i, $i);
                    }
                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], " ", gT("No answer"));
                    }
                    break;

                case "N": // Simple Numerical questions

                    // Only Show No-Answer if question is not mandatory
                    if ($rows['mandatory'] != 'Y')
                    {
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], " ", gT("No answer"));
                    }
                    break;

                default:

                    $aresult = Answer::model()->findAllByAttributes(array(
                        'qid' => $rows['qid'],
                        'scale_id' => 0,
                        'language' => $this->language
                    ), array('order' => 'sortorder, answer'));

                    foreach ($aresult as $arows)
                    {
                        $theanswer = addcslashes($arows['answer'], "'");
                        $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], $arows['code'], $theanswer);
                    }
                    if ($rows['type'] == "D")
                    {
                        // Only Show No-Answer if question is not mandatory
                        if ($rows['mandatory'] != 'Y')
                        {
                            $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], " ", gT("No answer"));
                        }
                    }
                    elseif ($rows['type'] != "M" &&
                        $rows['type'] != "P" &&
                        $rows['type'] != "J" &&
                        $rows['type'] != "I" )
                    {
                        // For dropdown questions
                        // optinnaly add the 'Other' answer
                        if ( (    $rows['type'] == "L" ||
                            $rows['type'] == "!") &&
                            $rows['other'] == "Y" )
                        {
                            $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], "-oth-", gT("Other"));
                        }

                        // Only Show No-Answer if question is not mandatory
                        if ($rows['mandatory'] != 'Y')
                        {
                            $canswers[]=array($rows['sid'].$X.$rows['gid'].$X.$rows['qid'], " ", gT("No answer"));
                        }
                    }
                    break;
                }//switch row type
            } //else
        } //foreach theserows

        return array($cquestions, $canswers);
    }

    /**
     * @param int $qid
     * @param int $gid
     * @param array $conditionsList
     * @return string html
     */
    protected function getCopyForm($qid, $gid, array $conditionsList, array $pquestions)
    {
        $this->registerScriptFile('ADMIN_SCRIPT_PATH', 'checkgroup.js');

        $url = $this->getcontroller()->createUrl(
            '/admin/conditions/sa/index/subaction/copyconditions/surveyid/',
            array(
                'surveyid' => $this->iSurveyID,
                'gid' => $gid,
                'qid' => $qid
            )
        );

        $data['url'] = $url;
        $data['conditionsList'] = $conditionsList;
        $data['pquestions'] = $pquestions;
        $data['qid'] = $qid;
        $data['gid'] = $gid;
        $data['iSurveyID'] = $this->iSurveyID;

        return $this->getController()->renderPartial(
            '/admin/conditions/includes/copyform',
            $data,
            true
        );
    }

    /**
     * Get html for add/edit condition form
     * @param array $args
     * @return void
     */
    protected function getEditConditionForm(array $args)
    {
        extract($args);
        $aViewUrls = array('output' => '');

        $mytitle = ($subaction == "editthiscondition" &&  isset($p_cid))?gT("Edit condition"):gT("Add condition");
        $scenario = '';
        $showScenario = ( ( $subaction != "editthiscondition" && isset($scenariocount) && ($scenariocount == 1 || $scenariocount==0)) || ( $subaction == "editthiscondition" && $scenario == 1) )?true:false;

        $js_getAnswers_onload = $this->getJsAnswersToSelect($cquestions, $p_cquestions, $p_canswers);

        $this->registerScriptFile('ADMIN_SCRIPT_PATH', 'conditions.js');

        if ($subaction == "editthiscondition" && isset($p_cid))
        {
            $submitLabel = gT("Update condition");
            $submitSubaction = "updatecondition";
            $submitcid = sanitize_int($p_cid);
        }
        else
        {
            $submitLabel = gT("Add condition");
            $submitSubaction = "insertcondition";
            $submitcid = "";
        }

        $data = array(
            'subaction'     => $subaction,
            'iSurveyID'     => $iSurveyID,
            'gid'           => $gid,
            'qid'           => $qid,
            'mytitle'       => $mytitle,
            'showScenario'  => $showScenario,
            'qcountI'       => $qcount+1,
            'cquestions'    => $cquestions,
            'p_csrctoken'   => $p_csrctoken,
            'p_prevquestionsgqa'  => $p_prevquestionsgqa,
            'tokenFieldsAndNames' => getTokenFieldsAndNames($iSurveyID),
            'method'        => $method,
            'subaction'     => $subaction,
            'EDITConditionConst'  => $this->getEDITConditionConst($subaction),
            'EDITConditionRegexp' => $this->getEDITConditionRegexp($subaction),
            'submitLabel'   => $submitLabel,
            'submitSubaction'     => $submitSubaction,
            'submitcid'     => $submitcid
        );
        $aViewUrls['output'] .= $this->getController()->renderPartial('/admin/conditions/includes/form_editconditions_header', $data, true);

        $aViewUrls['output'] .= "<script type='text/javascript'>\n"
            . "<!--\n"
            . "\t".$js_getAnswers_onload."\n";
        if (isset($p_method))
        {
            $aViewUrls['output'] .= "\tdocument.getElementById('method').value='".$p_method."';\n";
        }

        $aViewUrls['output'] .= $this->getEditFormJavascript($subaction);

        if (isset($p_scenario))
        {
            $aViewUrls['output'] .= "\tdocument.getElementById('scenario').value='".$p_scenario."';\n";
        }
        $aViewUrls['output'] .= "-->\n"
            . "</script>\n";

        return $aViewUrls['output'];
    }

    /**
     * @param array $cquestions
     * @param string $p_cquestions Question SGID
     * @param array $p_canswers E.g. array('A2')
     * @return string JS code
     */
    protected function getJsAnswersToSelect($cquestions, $p_cquestions, $p_canswers)
    {
        $js_getAnswers_onload = "";
        foreach ($cquestions as $cqn) {
            if ($cqn[3] == $p_cquestions) {
                if (isset($p_canswers)) {
                    $canswersToSelect = "";
                    foreach ($p_canswers as $checkval) {
                        $canswersToSelect .= ";$checkval";
                    }
                    $canswersToSelect = substr($canswersToSelect,1);
                    $js_getAnswers_onload .= "$('#canswersToSelect').val('$canswersToSelect');\n";
                }
            }
        }
        return $js_getAnswers_onload;
    }

    /**
     * @param string $subaction
     * @return string
     */
    protected function getEDITConditionConst($subaction)
    {
        $EDITConditionConst = '';
        if ($subaction == "editthiscondition") {
            if (isset($_POST['EDITConditionConst']) && $_POST['EDITConditionConst'] != '') {
                $EDITConditionConst=HTMLEscape($_POST['EDITConditionConst']);
            }
        }
        else {
            if (isset($_POST['ConditionConst']) && $_POST['ConditionConst'] != '') {
                $EDITConditionConst=HTMLEscape($_POST['ConditionConst']);
            }
        }
        return $EDITConditionConst;
    }

    /**
     * @param string $subaction
     * @return string
     */
    protected function getEDITConditionRegexp($subaction)
    {
        $EDITConditionRegexp = '';
        if ($subaction == "editthiscondition") {
            if (isset($_POST['EDITConditionRegexp']) && $_POST['EDITConditionRegexp'] != '') {
                $EDITConditionRegexp=HTMLEscape($_POST['EDITConditionRegexp']);
            }
        }
        else {
            if (isset($_POST['ConditionRegexp']) && $_POST['ConditionRegexp'] != '') {
                $EDITConditionRegexp=HTMLEscape($_POST['ConditionRegexp']);
            }
        }
        return $EDITConditionRegexp;
    }

    /**
     * Generates some JS used by form
     * @param string $subaction
     * @return string JS
     */
    protected function getEditFormJavascript($subaction)
    {
        $aViewUrls = array('output' => '');
        if ($subaction == "editthiscondition")
        { // in edit mode we read previous values in order to dusplay them in the corresponding inputs
            if (isset($_POST['EDITConditionConst']) && $_POST['EDITConditionConst'] != '')
            {
                // In order to avoid issues with backslash escaping, I don't use javascript to set the value
                // Thus the value is directly set when creating the Textarea element
                //$aViewUrls['output'] .= "\tdocument.getElementById('ConditionConst').value='".HTMLEscape($_POST['EDITConditionConst'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#CONST';\n";
            }
            elseif (isset($_POST['EDITprevQuestionSGQA']) && $_POST['EDITprevQuestionSGQA'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('prevQuestionSGQA').value='".HTMLEscape($_POST['EDITprevQuestionSGQA'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#PREVQUESTIONS';\n";
            }
            elseif (isset($_POST['EDITtokenAttr']) && $_POST['EDITtokenAttr'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('tokenAttr').value='".HTMLEscape($_POST['EDITtokenAttr'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#TOKENATTRS';\n";
            }
            elseif (isset($_POST['EDITConditionRegexp']) && $_POST['EDITConditionRegexp'] != '')
            {
                // In order to avoid issues with backslash escaping, I don't use javascript to set the value
                // Thus the value is directly set when creating the Textarea element
                //$aViewUrls['output'] .= "\tdocument.getElementById('ConditionRegexp').value='".HTMLEscape($_POST['EDITConditionRegexp'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#REGEXP';\n";
            }
            elseif (isset($_POST['EDITcanswers']) && is_array($_POST['EDITcanswers']))
            { // was a predefined answers post
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#CANSWERSTAB';\n";
                $aViewUrls['output'] .= "\t$('#canswersToSelect').val('".$_POST['EDITcanswers'][0]."');\n";
            }

            if (isset($_POST['csrctoken']) && $_POST['csrctoken'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('csrctoken').value='".HTMLEscape($_POST['csrctoken'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editSourceTab').value='#SRCTOKENATTRS';\n";
            }
            else if (isset($_POST['cquestions']) && $_POST['cquestions'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('cquestions').value='".HTMLEscape($_POST['cquestions'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editSourceTab').value='#SRCPREVQUEST';\n";
            }
        }
        else
        { // in other modes, for the moment we do the same as for edit mode
            if (isset($_POST['ConditionConst']) && $_POST['ConditionConst'] != '')
            {
                // In order to avoid issues with backslash escaping, I don't use javascript to set the value
                // Thus the value is directly set when creating the Textarea element
                //$aViewUrls['output'] .= "\tdocument.getElementById('ConditionConst').value='".HTMLEscape($_POST['ConditionConst'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#CONST';\n";
            }
            elseif (isset($_POST['prevQuestionSGQA']) && $_POST['prevQuestionSGQA'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('prevQuestionSGQA').value='".HTMLEscape($_POST['prevQuestionSGQA'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#PREVQUESTIONS';\n";
            }
            elseif (isset($_POST['tokenAttr']) && $_POST['tokenAttr'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('tokenAttr').value='".HTMLEscape($_POST['tokenAttr'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#TOKENATTRS';\n";
            }
            elseif (isset($_POST['ConditionRegexp']) && $_POST['ConditionRegexp'] != '')
            {
                // In order to avoid issues with backslash escaping, I don't use javascript to set the value
                // Thus the value is directly set when creating the Textarea element
                //$aViewUrls['output'] .= "\tdocument.getElementById('ConditionRegexp').value='".HTMLEscape($_POST['ConditionRegexp'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#REGEXP';\n";
            }
            else
            { // was a predefined answers post
                if (isset($_POST['cquestions']))
                {
                    $aViewUrls['output'] .= "\tdocument.getElementById('cquestions').value='".HTMLEscape($_POST['cquestions'])."';\n";
                }
                $aViewUrls['output'] .= "\tdocument.getElementById('editTargetTab').value='#CANSWERSTAB';\n";
            }

            if (isset($_POST['csrctoken']) && $_POST['csrctoken'] != '')
            {
                $aViewUrls['output'] .= "\tdocument.getElementById('csrctoken').value='".HTMLEscape($_POST['csrctoken'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editSourceTab').value='#SRCTOKENATTRS';\n";
            }
            else
            {
                if (isset($_POST['cquestions'])) $aViewUrls['output'] .= "\tdocument.getElementById('cquestions').value='".javascriptEscape($_POST['cquestions'])."';\n";
                $aViewUrls['output'] .= "\tdocument.getElementById('editSourceTab').value='#SRCPREVQUEST';\n";
            }
        }
        return $aViewUrls['output'];
    }

    /**
     * The navigator that lets user quickly move to another question within the survey.
     * @param array $theserows
     * @param array $postrows
     * @param array $args
     * @return string html
     */
    protected function getQuestionNavOptions($theserows, $postrows, $args)
    {
        extract($args);

        $theserows2 = array();
        foreach ($theserows as $row) {
            $question = strip_tags($row['question']);
            $questionselecter = viewHelper::flatEllipsizeText($question,true,'40');
            $theserows2[] = array(
                'value' => $this->createNavigatorUrl($row['gid'], $row['qid']),
                'text' => strip_tags($row['title']) . ':' . $questionselecter
            );
        }

        $postrows2 = array();
        foreach ($postrows as $row) {
            $question = strip_tags($row['question']);
            $questionselecter = viewHelper::flatEllipsizeText($question,true,'40');
            $postrows2[] = array(
                'value' => $this->createNavigatorUrl($row['gid'], $row['qid']),
                'text' => strip_tags($row['title']) . ':' . $questionselecter
            );
        }

        $data = array(
            'theserows' => $theserows2,
            'postrows' => $postrows2,
            'currentValue'=> $this->createNavigatorUrl($gid, $qid),
            'currentText' => $questiontitle . ':' . viewHelper::flatEllipsizeText(strip_tags($sCurrentFullQuestionText), true, '40')
        );

        return $this->getController()->renderPartial('/admin/conditions/includes/navigator', $data, true);
    }

    /**
     * @param int $gid Group id
     * @param int $qid Questino id
     * @return string url
     */
    protected function createNavigatorUrl($gid, $qid)
    {
        return $this->getController()->createUrl(
            '/admin/conditions/sa/index/subaction/editconditionsform/',
            array(
                'surveyid' => $this->iSurveyID,
                'gid' => $gid,
                'qid' => $qid
            )
        );
    }

    /**
     * Javascript to match question with answer
     * @param array $canswers
     * @param array $cquestions
     * @param boolean $surveyIsAnonymized
     * @return string js
     */
    protected function getJavascriptForMatching(array $canswers, array $cquestions, $surveyIsAnonymized)
    {
        $javascriptpre = CHtml::openTag('script', array('type' => 'text/javascript'))
            . "<!--\n"
            . "\tvar Fieldnames = new Array();\n"
            . "\tvar Codes = new Array();\n"
            . "\tvar Answers = new Array();\n"
            . "\tvar QFieldnames = new Array();\n"
            . "\tvar Qcqids = new Array();\n"
            . "\tvar Qtypes = new Array();\n";

        $jn = 0;
        foreach($canswers as $can) {
            $an = ls_json_encode(flattenText($can[2]));
            $javascriptpre .= "Fieldnames[{$jn}]='{$can[0]}';\n"
                . "Codes[{$jn}]='{$can[1]}';\n"
                . "Answers[{$jn}]={$an};\n";
            $jn++;
        }

        $jn = 0;
        foreach ($cquestions as $cqn) {
            $javascriptpre .= "QFieldnames[$jn]='$cqn[3]';\n"
                ."Qcqids[$jn]='$cqn[1]';\n"
                ."Qtypes[$jn]='$cqn[2]';\n";
            $jn++;
        }

        //  record a JS variable to let jQuery know if survey is Anonymous
        if ($surveyIsAnonymized) {
            $javascriptpre .= "isAnonymousSurvey = true;";
        }
        else {
            $javascriptpre .= "isAnonymousSurvey = false;";
        }

        $javascriptpre .= "//-->\n"
            .CHtml::closeTag('script');

        return $javascriptpre;
    }
}
