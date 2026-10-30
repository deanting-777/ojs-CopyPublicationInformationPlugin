<?php

/**
 * @file plugins/generic/copyPublicationInformation/CopyPublicationInformation.php
 *
 * Copyright (c) 2026 Dean Ting
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CopyPublicationInformationPlugin
 *
 * @brief Class for "Copy Publication Information" generic plugin
 */

namespace APP\plugins\generic\copyPublicationInformation;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class CopyPublicationInformationPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);

        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add(
                'TemplateManager::display',
                [$this, 'callbackTemplateDisplay']
            );
        }

        return $success;
    }

    public function callbackTemplateDisplay($hookName, $args)
    {
        $templateMgr = &$args[0];
        $template = &$args[1];

        if ($template === 'frontend/pages/article.tpl') {

            $templateMgr->registerFilter(
                'output',
                [$this, 'articlePageOutputFilter']
            );

        }

        return false;
    }

    public function articlePageOutputFilter($output, $templateMgr)
    {
        $marker='<!-- COPY_PUBLICATION_INFORMATION -->';

        if(strpos($output, $marker)!==false){
            return $output;
        }

        $target='<div class="item published">';
        $position =strpos($output,$target);

        if ($position===false){
            return $output;
        }



        $publication=$templateMgr->getTemplateVars('publication');
        $article=$templateMgr->getTemplateVars('article');
        $journal=$templateMgr->getTemplateVars('currentContext');
        $issue=$templateMgr->getTemplateVars('issue');
        $section=$templateMgr->getTemplateVars('section');

        $title=$publication->getLocalizedTitle();
        $journalName=$journal->getLocalizedName();

        $str=__('user.title').':'.$title;

        // Authors
        $authorNames=[];
        if($publication->getData('authors')){
            foreach($publication->getData('authors') as $item){
                $authorName=$item->getFullName();
                $authorNames[]=$authorName;

                $str.="\n\n".__('user.role.author').':'.$authorName;

                //Affiliations
                $affiliations=$item->getAffiliations();
                foreach($affiliations as $affiliation){
                    $affiliationName=$affiliation->getLocalizedName();
                    if(!empty($affiliationName)){
                        $str.="\n".__('user.affiliation').':'.$affiliationName;
                    }
                }
            }
        }

        //Journal
        $str.="\n\n".__('context.context').':'.$journalName;


        //Keywords
        $keywordData=$publication->getLocalizedData('keywords');
        $keywordNames=[];

        if(is_array($keywordData)){
            foreach($keywordData as $keyword){
                if(is_array($keyword)){
                    $keywordName=$keyword['name'] ?? '';
                }
                else if(is_object($keyword) && isset($keyword->name)){
                    $keywordName=$keyword->name;
                }
                else{
                    $keywordName=$keyword;
                }

                if(is_string($keywordName) && trim($keywordName)!==''){
                    $keywordNames[]=trim($keywordName);
                }
            }
        }
        elseif(is_string($keywordData) && trim($keywordData)!==''){
            $keywordNames[]=trim($keywordData);
        }

        if($keywordNames){
            $str.="\n\n".__('common.keywords').":".implode(', ',$keywordNames);
        }

        //Published
        $publishedDate=$publication->getData('datePublished');
        $publishedTimestamp=$publishedDate?strtotime($publishedDate):false;

        if ($publishedTimestamp!==false){
            $str.="\n\n".__('submission.status.published').":".date('M j, Y',$publishedTimestamp);
        }


        //Issue
        $volume='';
        $number='';
        $issueYear='';

        if($issue){
            $volume=trim((string) $issue->getData('volume'));
            $number=trim((string) $issue->getData('number'));
            $issueYear=trim((string) $issue->getData('year'));

            if(empty($issueYear)){
                $issueDate=$issue->getData('datePublished');

                if($issueDate && strtotime($issueDate)!==false){
                    $issueYear=date('Y',strtotime($issueDate));
                }
            }

            $issueParts=[];

            if($volume!==''){
                $issueParts[]=__('issue.vol').$volume;
            }

            if($number!==''){
                $issueParts[]=__('issue.no').$number;
            }

            $issueText=implode(' ',$issueParts);

            if($issueYear!==''){
                $issueText.=' ('.$issueYear.')';
            }

            if($issueText!==''){
                $str.="\n\n".__('issue.issue').":".$issueText;
            }
            else{
                $str.="\n\n".__('issue.issue').":".$issue->getIssueIdentification();
            }
        }

        //Pages
        $pages=$publication->getData('pages');

        if(!empty($pages)){
            $str.="\n\n".__('editor.issues.pages').":".$pages;
        }

        //Section
        if($section){
            $sectionName=$section->getLocalizedTitle();

            if(!empty($sectionName)){
                $str.="\n\n".__('section.section').":".$sectionName;
            }
        }

        //DOI
        $doi=$publication->getStoredPubId('doi');
        if(!empty($doi)){
            $doiUrl='https://doi.org/'.$doi;
            $str.="\n\nDOI:".$doiUrl;
        }

        //abstract
        $abstract=$publication->getLocalizedData('abstract');
        if(!empty($abstract)){
            $abstract=html_entity_decode(strip_tags(preg_replace('/<\s*br\s*\/?>|<\/p>/i',"\n",$abstract)),ENT_QUOTES,'UTF-8');
            $str.="\n\n".__('article.abstract').":".$abstract;
        }

        //Article URL
        $articleUrl='';
        if($article && $journal){
            $request=\APP\core\Application::get()->getRequest();
            $articleUrl=$request->getDispatcher()->url($request,\PKP\core\PKPApplication::ROUTE_PAGE,$journal->getPath(),'article','view',[$article->getBestId()]);
            $str.="\n\n".__('article.article')." ".__('common.url').":".$articleUrl;
        }

        //Full Text (PDF)
        $pdfUrl='';
        $primaryGalleys=$templateMgr->getTemplateVars('primaryGalleys');
        if(is_array($primaryGalleys)){
            foreach($primaryGalleys as $galley){
                if(!method_exists($galley, 'isPdfGalley') || !$galley->isPdfGalley()){
                    continue;
                }

                $galleyPath=[];

                if($publication->getId()!=$article->getData('currentPublicationId')){
                    $galleyPath=[$article->getBestId(),'version',$publication->getId(),$galley->getBestGalleyId()];
                }
                else{
                    $galleyPath=[
                        $article->getBestId(),
                        $galley->getBestGalleyId()
                    ];
                }

                $pdfUrl=$request->getDispatcher()->url($request,\PKP\core\PKPApplication::ROUTE_PAGE,$journal->getPath(),'article','view',$galleyPath);
                break;
            }
        }

        if(!empty($pdfUrl)){
            $str.="\n".__('search.fullText')."(".__('stats.pdf')."):".$pdfUrl;
        }

        //How to Cite
        $year=$publishedTimestamp!==false?date('Y',$publishedTimestamp):$issueYear;
        $howToCite='';

        if($authorNames){
            $howToCite.=implode(', ',$authorNames).' ';
        }

        if($year){
            $howToCite.='('.$year.'). ';
        }

        $howToCite.=$title.'. ';
        $howToCite.=$journalName;

        if ($volume!=='' || $number!=='') {
            $howToCite.=', ';

            if($volume!==''){
                $howToCite.=$volume;
            }

            if($number!==''){
                $howToCite.='('.$number.')';
            }
        }

        if(!empty($pages)){
            $howToCite.=', '.$pages;
        }

        $howToCite.='.';

        if(!empty($doi)){
            $howToCite.=' https://doi.org/'.$doi;
        }
        elseif(!empty($articleUrl)){
            $howToCite.=' '.$articleUrl;
        }

        $str.="\n\n".__('submission.howToCite').":".$howToCite;

        $str=preg_replace('/<br\s*\/?>/i',"\n",$str);
        $json=json_encode($str,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
        if($json===false){
            $json='""';
        }

        $templateMgr->assign('publicationInfomation',$json);

        $content=$templateMgr->fetch(
            $this->getTemplateResource('publicationInformation.tpl')
        );
        $insert=$marker."\n".$content."\n";

        return substr($output, 0, $position).$insert.substr($output, $position);
    }

    /**
     * Install default settings on system install.
     *
     * @return string
     */
    public function getInstallSitePluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Install default settings on journal creation.
     *
     * @return string
     */
    public function getContextSpecificPluginSettingsFile()
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Get the display name of this plugin.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.copyPublicationInformation.displayName');
    }

    /**
     * Get a description of the plugin.
     */
    public function getDescription()
    {
        return __('plugins.generic.copyPublicationInformation.description');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\copyPublicationInformation\CopyPublicationInformationPlugin', '\CopyPublicationInformationPlugin');
}
