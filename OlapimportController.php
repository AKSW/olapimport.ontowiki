<?php

/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Component controller for the CSV Importer.
 *
 * @category OntoWiki
 * @package Extensions
 * @subpackage Csvimport
 * @copyright Copyright (c) 2010, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
include("clsDatabaseSchema.php");

class OlapimportController extends OntoWiki_Controller_Component
{               
    public function init()
    {
        // init component
        parent::init();

        //$this->view->headScript()->appendFile($this->_componentUrlBase . 'scripts/csvimport.js');
        $this->view->headScript()->appendFile($this->_componentUrlBase . 'scripts/rdfa.object.js');
    $this->view->headScript()->appendFile($this->_componentUrlBase . 'scripts/olapimport.js');
    }

    public function indexAction()
    {   
    $this->_forward('connection');
    }

    public function connectionAction()
    {        
        if (!isset($this->_request->connection)) 
        {
            // clean store
            $this->_destroySessionStore();

            // TODO: show import dialogue and import file
            $this->view->placeholder('main.window.title')->append('Import OLAP Data');
            OntoWiki_Navigation::disableNavigation();

            $this->view->formActionUrl = $this->_config->urlBase . 'olapimport';
            $this->view->formEncoding  = 'multipart/form-data';
            $this->view->formClass     = 'simple-input input-justify-left';
            $this->view->formMethod    = 'post';
            $this->view->formName      = 'import';
            $this->view->referer       = isset($_SERVER['HTTP_REFERER']) ? urlencode($_SERVER['HTTP_REFERER']) : '';

            $this->view->modelUri   = (string)$this->_owApp->selectedModel;
            $this->view->title      = 'Database Connection';
            $model = $this->_owApp->selectedModel;
            if($model != null)
            {
                $this->view->modelTitle = $model->getTitle();

                if ($model->isEditable()) {
                    $toolbar = $this->_owApp->toolbar;
                    $toolbar->appendButton(OntoWiki_Toolbar::SUBMIT, array('name' => 'Establish Database Connection', 'id' => 'import'))
                            ->appendButton(OntoWiki_Toolbar::RESET, array('name' => 'Cancel'));
                    $this->view->placeholder('main.window.toolbar')->set($toolbar);
                } 
                else 
                {
                    $this->_owApp->appendMessage(
                        new OntoWiki_Message('No write permissions on model \''.$this->view->modelTitle.'\'', OntoWiki_Message::WARNING)
                    );
                }

                // FIX: http://www.webmasterworld.com/macintosh_webmaster/3300569.htm
                // disable connection keep-alive
                $response = $this->getResponse();
                $response->setHeader('Connection', 'close', true);
                $response->sendHeaders();
            return;
            } else {
                $this->_owApp->appendMessage(
                        new OntoWiki_Message('You need to select a model first', OntoWiki_Message::WARNING)
                    );
            }
        } 
        else 
        {
            // evaluate post data
            $messages = array();
            $post = $this->_request->getPost();
            $database = $post['database'];
            $ip = $post['ip'];
            $user = $post['user'];
            $pwd = $post['password'];
            
            $errorFlag = false;

        $dbhandle = mysql_connect($ip, $user, $pwd);

            switch (true) 
            {
                case ($dbhandle == false):
                    $message = 'Unable to connect to MySQL server.';
                        $this->_owApp->appendMessage(
                            new OntoWiki_Message($message, OntoWiki_Message::ERROR)
                        );
                        $errorFlag = true;
                        break;
            
                case ($database == ""):
                    $message = 'No database selected. Please try again.';
                        $this->_owApp->appendMessage(
                            new OntoWiki_Message($message, OntoWiki_Message::ERROR)
                        );
                        $errorFlag = true;
                      break;
            }
            $store = $this->_getSessionStore();
        $store->ip = $ip;
            $store->user = $user;
        $store->password = $pwd;
            $store->database = $database;
        $store->handle = $dbhandle;
            // $store->nextAction   = 'mapping';
            // now we map
            $this->_forward('database');
        }
    }
    
    public function databaseAction()
    {
        $store = $this->_getSessionStore();
        
            $objSchema = new clsDatabaseSchema($store->ip, $store->user,$store->password);
            $store->attributes = $objSchema->ListAttributes($store->database);
            $this->view->attributes = $store->attributes;
            $this->view->modelUri   = (string)$this->_owApp->selectedModel;
    
        
    }   

    public function mappingAction()
    {
        $store = $this->_getSessionStore();
        
        require_once('DataCubeimporter.php');
        $importer = new DataCubeImporter($this->view, $this->_privateConfig);
        //esto viene del archivo default.ini
        //print_r($this->_privateConfig);
        $json=".";
        if (!empty($this->_request->mapping)) {
            $json = $this->_request->mapping;
            $json = str_replace('\\"', '"', $json);
            $configuration = json_decode($json, true);
        }

        if ($configuration) 
        {
            $server = "localhost";
            $user = "root";
            $pwd = "";
            $db = "datagov";

            $importer->setConfiguration($configuration);
            $importer->databaseConn($server, $user, $pwd, $db);
            $store->results = $importer->importData();
            //$this->_helper->viewRenderer->setNoRender();
        } 
        //$this->view->preview=$json; 
    }

    protected function resultsAction()
    {
        $this->view->placeholder('main.window.title')->append('Import CSV Results');
        OntoWiki_Navigation::disableNavigation();
    
    }

    protected function cubesAction()
    {
    $store = $this->_getSessionStore(); 
    $objSchema = new clsDatabaseSchema("localhost", "root", ""); 
    $dimensions= $objSchema->ListDimensions("datagov",  $this->_request->table );
    $this->view->dimensions = $dimensions;

    OntoWiki_Navigation::disableNavigation();

    }
    protected function previewAction()
    {
    $store = $this->_getSessionStore();
    if ($store->handle == true) 
    {
        $objSchema = new clsDatabaseSchema($store->ip, $store->user, $store->password); 
        $preview= $objSchema->ListPreview($store->database);
        //$this->view->preview = $preview;
        $this->view->preview=$this->_request->dimensions;   
    }   
    OntoWiki_Navigation::disableNavigation();
    
    }
    protected function extractAction()
    {
    $store = $this->_getSessionStore();
    $configuration = null;
    
    require_once('DataCubeImporter.php');
    $importer = new DataCubeImporter($this->view, $this->_privateConfig);
    //esto viene del archivo default.ini
    //print_r($this->_privateConfig);
    //if (!empty($this->_request->dimensions)) {
    // $json = $this->_request->dimensions;
    $json="";
    $json = str_replace('\\"', '"', $json);
    $configuration = json_decode($json, true);
    $configuration="sei la";
    //}


    //$importer->setFile($store->importedFile);

        
            if ($configuration) {
                $importer->setConfiguration($configuration);
                $importer->setParsedFile($store->parsedFile);
                $store->results = $importer->importData();
                $this->_helper->viewRenderer->setNoRender();
            } else {
                //get stored Configurations
                $importer->setStoredConfigurations($this->getStoredConfigurationUris());
                $importer->createConfigurationView($this->_config->urlBase);
                $store->parsedFile = $importer->getParsedFile();
            }   
    $this->view->configuration=$configuration;
    }
    protected function getStoredConfigurationUris() {
        $dir = $this->_owApp->extensionManager->getExtensionPath('olapimport').'/configs/';
        if(!is_dir($dir)) return;
        $configurations = array();

        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if($file == "." || $file == '..') continue;

                $handle = fopen($dir.$file, 'r');
                $contents = fread($handle, filesize($dir.$file));
                fclose($handle);

                $configurations[] = array (
                                            'label' => str_replace('.cfg', '', str_replace('_', ' ', $file)),
                                            'config' => $contents );
            }
            closedir($dh);
            
            return $configurations;
        }
        return array();

        return;
        $sysontUri  = $this->_owApp->erfurt->getConfig()->sysont->modelUri;
        $sysOnt     = $this->_owApp->erfurt->getStore()->getModel($sysontUri, false);

        $query = new Erfurt_Sparql_SimpleQuery();
        $query->setProloguePart(' SELECT  ?configUri ?configLabel ?configuration') ;
        $query->setWherePart('  
                    WHERE { ?configUri a <' . $sysontUri . 'OLAPImportConfig> .
                            ?configUri <http://www.w3.org/2000/01/rdf-schema#label> ?configLabel .
                            ?configUri <' . $sysontUri . 'OLAPImportConfig/configuration> ?configuration} ');

        if ($result = $sysOnt->sparqlQuery($query)) {
            // var_dump($result); die;
            $configurations = array();
            foreach ($result as $entry) {
                //var_dump($entry['configuration']); die;
                $configurations[$entry['configUri']] = array ( 
                                                        'label' => $entry['configLabel'],
                                                        'config' => base64_decode($entry['configuration']) );
            }
//var_dump($configurations);
            return $configurations;
        }
        return array();
    }

    protected function saveconfigAction(){
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        if( $this->_createBaseModel() ){
            // get post params
            $post = $this->_request->getPost();

            $val = $post['configString'];
            $name = str_replace(" ", "_", $post['configName']);

            $fp = fopen('extensions/components/OLAPimport/configs/'.$name.'.cfg', 'w');
            fwrite($fp, $val);
            fclose($fp);

            return;

            // needed vars
            $sysontUri = $this->_owApp->erfurt->getConfig()->sysont->modelUri;
            $sysOnt = $this->_owApp->erfurt->getStore()->getModel($sysontUri, false);
            $class = $sysontUri.'OLAPImportConfig';
            $config = $class.'/configuration';
            $type = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
            $label = 'http://www.w3.org/2000/01/rdf-schema#label';
            $val = $post['configString'];
            $name = $sysontUri . date('Y/m/d/H/i/s') . '/' . str_replace(' ', '_', $post['configName']);

            // create config instance:
            // name
            // config string
            // {sysont_ns}:salt/name a {sysont_ns}:CSVImportConfig
            // {sysont_ns}:salt/name {sysont_ns}:CSVImportConfig/configuration "config string"
            $element[$name] = array(
                $type => array(
                    array(
                        'type' => 'uri',
                        'value' => $class
                    )
                ),
                $config => array(
                    array(
                        'type' => 'literal',
                        'value' => base64_encode($val)
                    )
                ),
                $label => array(
                    array(
                        'type' => 'literal',
                        'value' => $post['configName']
                    )
                )
            );

            //var_dump($element);
            $sysOnt->addMultipleStatements( $element );
        }
    }

    protected function _createBaseModel(){
        // check access controll for SysOnt
        $sysontUri = $this->_owApp->erfurt->getConfig()->sysont->modelUri;
        $sysOnt = $this->_owApp->erfurt->getStore()->getModel($sysontUri, false);
        $allow = $this->_owApp->erfurt->getAc()->isModelAllowed('edit', $sysOnt);
        if ($allow) {
            // create config class
            // {sysont_ns}:CSVImportConfig
            // {sysont_ns}:CSVImportConfig rdfs:label "Configuration Class"

            $s = $sysontUri.'OLAPImportConfig';
            $type = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
            $label = 'http://www.w3.org/2000/01/rdf-schema#label';
            $element[$s] = array(
                $type => array(
                    array(
                        'type' => 'uri',
                        'value' => 'http://www.w3.org/2002/07/owl#Class'
                    )
                ),
                $label => array(
                    array(
                        'type' => 'literal',
                        'value' => 'OLAP Import Configuration'
                    )
                )
            );

            $sysOnt->addMultipleStatements( $element );

            // create config string property
            // {sysont_ns}:CSVImportConfig/configuration
            // {sysont_ns}:CSVImportConfig/configuration rdfs:label "Configuration"
            // {sysont_ns}:CSVImportConfig/configuration rdfs:domain {sysont_ns}:CSVImportConfig

            $element = array();
            $sp = $s.'/configuration';
            $label = 'http://www.w3.org/2000/01/rdf-schema#label';
            $domain = 'http://www.w3.org/2000/01/rdf-schema#domain';
            $element[$sp] = array(
                $domain => array(
                    array(
                        'type' => 'uri',
                        'value' => $s
                    )
                ),
                $label => array(
                    array(
                        'type' => 'literal',
                        'value' => 'configuration'
                    )
                )
            );

            $sysOnt->addMultipleStatements( $element );

            return true;
        }else{
            return false;
        }
    }

    protected function _getSessionStore()
    {
        $session = new Zend_Session_Namespace('OLAP_IMPORT_SESSION');
        return $session;
    }

    protected function _destroySessionStore(){
        Zend_Session::namespaceUnset('OLAP_IMPORT_SESSION');
    }
}