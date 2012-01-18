<?php

/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
 require_once("Importer.php");
/**
 * Component controller for the OLAP Importer.
 *
 * @category OntoWiki
 * @package Extensions
 * @subpackage Csvimport
 * @copyright Copyright (c) 2010, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class DataCubeImporter extends Importer
{
    protected $measure;
    protected $components;
    protected $dbhandle;
   
    public function parseFile() {
        $this->logEvent("Parsing file..");
        require_once 'CsvParser.php';
        $parser = new CsvParser($this->uploadedFile);
        $this->parsedFile = array_filter($parser->getParsedFile());
        $this->logEvent("File parsed!");
    }

    public function databaseConn($in_server, $in_user, $in_pwd, $in_db)
    {
        $this->dbhandle = mysql_connect($in_server, $in_user, $in_pwd);
        mysql_select_db($in_db);
    }

    public function importData() {
        $this->logEvent("Import started..");
        $this->db_createDimension();
        $this->db_createDataset();
        $this->db_saveData();
        $this->logEvent("Done saving data!");
    }

    public function createConfigurationView($urlBase) {
        $ontowiki = OntoWiki::getInstance();
        $model = $ontowiki->selectedModel;

        $this->view->scovo = true;

        $this->view->placeholder('main.window.title')->append('Import CSV Data');
        $this->view->actionUrl = $urlBase . 'csvimport/mapping';
        $this->view->salt = hash("md5", serialize($this->parsedFile));
        OntoWiki_Navigation::disableNavigation();

        if ($model->isEditable()) {

            $toolbar = $ontowiki->toolbar;
            $toolbar->appendButton(OntoWiki_Toolbar::ADD, array('name' => 'Add Dimension', 'id' => 'btn-add-dimension'))
                ->appendButton(OntoWiki_Toolbar::ADD, array('name' => 'Add Attribute', 'id' => 'btn-attribute', 'class'=>''))
                ->appendButton(OntoWiki_Toolbar::EDIT, array('name' => 'Select Data Range', 'id' => 'btn-datarange', 'class'=>''))
                ->appendButton(OntoWiki_Toolbar::SEPARATOR)
                ->appendButton(OntoWiki_Toolbar::SUBMIT, array('name' => 'Extract Triples', 'id' => 'extract'))
                ->appendButton(OntoWiki_Toolbar::RESET, array('name' => 'Cancel'));
            $this->view->placeholder('main.window.toolbar')->set($toolbar);


            $configurationMenu = OntoWiki_Menu_Registry::getInstance()->getMenu('Configurations');
            $i = 0;
            $pattern = '/\'/i';
            $replacement = "\\'";
            $this->view->configs = array();
            if(isset($this->storedConfigurations))
			{
                foreach ($this->storedConfigurations as $configNum => $config) {
                    $this->view->configs[$i] = preg_replace($pattern, $replacement, $config['config']);
                    $configurationMenu->prependEntry(
                            'Select ' . $config['label'],
                            'javascript:useCSVConfiguration(csvConfigs['.$i.'])'
                    );
                    $i++;
                }
            }

        $menu = new OntoWiki_Menu();
        $menu->setEntry('Configurations', $configurationMenu);

        $event = new Erfurt_Event('onCreateMenu');
        $event->menu = $configurationMenu;
        $this->view->placeholder('main.window.menu')->set($menu->toArray(false, true));



        } else {
            $ontowiki->appendMessage(
                #new OntoWiki_Message("No write permissions on model '{$this->view->modelTitle}'", OntoWiki_Message::WARNING)
            );
        }

        $this->view->table = $this->view->partial('partials/table.phtml', array(
                    'data' => $this->parsedFile,
                    'tableClass' => 'csvimport'
                ));
    }

    protected function db_createDimension(){

        /****************************************************************/
        /*******************  Properties ********************************/
        /****************************************************************/

        $type = $this->componentConfig->class->type;
        $label = $this->componentConfig->class->label;
        $property = $this->componentConfig->class->Property;
        $value_predicate = $this->componentConfig->class->value;
        $subPropertyOf = $this->componentConfig->class->subPropertyOf;
        $comment = $this->componentConfig->class->comment;
        $range = $this->componentConfig->class->range;

        /****************************************************************/
        /*******************  Classes DataCube **************************/
        /****************************************************************/
        
        $qbDatastructDefinition = $this->componentConfig->qb->DataStructureDefinition;
        $qbDimensionProperty = $this->componentConfig->qb->DimensionProperty;
        $qbMeasureProperty = $this->componentConfig->qb->MeasureProperty;
        $qbObservation = $this->componentConfig->qb->Observation;
        $qbDataSet = $this->componentConfig->qb->DataSet;
        $obsValue = $this->componentConfig->local->incidence->subpropertyof;

        /*$sdmxdimension =  "http://purl.org/linked-data/sdmx/2009/dimension#";
        $sdmxmeasure =  "http://purl.org/linked-data/sdmx/2009/measure#";
        $sdmxattribute = "http://purl.org/linked-data/sdmx/2009/attribute#";
        $sdmxconcept = "http://purl.org/linked-data/sdmx/2009/concept#";
        $sdmxcode = "http://purl.org/linked-data/sdmx/2009/code#";
        $sdmxmetadata =  "http://purl.org/linked-data/sdmx/2009/metadata#";
        $sdmxsubject =  "http://purl.org/linked-data/sdmx/2009/subject#";*/
        
        /****************************************************************/
        /*******************  properties DataCube ***********************/
        /****************************************************************/
         
        $qbattribute = $this->componentConfig->qb->attribute;
        $component = $this->componentConfig->qb->component;
        $qbconcept = $this->componentConfig->qb->concept;

        $this->logEvent("Creating dimensions..");  
        $ontowiki = OntoWiki::getInstance();
        if( !isset($this->configuration) ) die ("config not set!");

        $elements = array();
        foreach ($this->configuration as $url => $dim) 
        {
            if($url == "uribase")
                continue;

            // if it's attribute
            if( isset($dim['attribute']) && $dim['attribute'] == true)
            {
                if(isset($dim['bytable']) && $dim['bytable'] == true)
                { 
                    $query_att  = "select ".$dim['column']." as label from ".$dim['table'];
                    $res = mysql_query( $query_att, $this->dbhandle );
                    while($row = mysql_fetch_array($res))
                    {
                        $elabel = $row['label'];
                        $element = array();

                        $eurl = $url."/".urlencode($elabel);

                        $element[$eurl] = array(

                            $type => array(
                            array(
                                'type' => 'uri',
                                'value' => $dim['uri']
                                )
                            ),
                            $label => array(
                                array(
                                    'type' => 'literal',
                                    'value' => $elabel
                                    )
                                )
                        );
                        $elements[] = $element;                  
                    }   
                }
                else
                {                
                    // save measure
                    $this->measures[] = array(
                        'url' => $url,
                        'uri' => $dim['uri'],
                        'label' => $dim['label']
                    );
                    
                    // empty array
                    $element = array();
                    // class
                    $element[$url] = array(
                        $type => array(
                            array(
                                'type' => 'uri',
                                'value' => $dim['uri']
                                )
                            ),
                        $label => array(
                            array(
                                'type' => 'literal',
                                'value' => $dim['label']
                                )
                            )
                    );
                }
                
                $elements[] = $element;
                continue;
            }

            if(isset($dim['dimension']) && $dim['dimension'] == true)
            {
                $element = array();

                // class
                $element[$url] = array(
                    $type => array(
                        array(
                            'type' => 'uri',
                            'value' => $qbDimensionProperty
                            )
                        ),
                    $label => array(
                        array(
                            'type' => 'literal',
                            'value' => $dim['label_dimension']
                            )
                        )
                );
                
                //cuando son todos numeros
                if( preg_match('/\D/', $dim['label_dimension']) <= 0  ){
                    $element[$url] = array_merge($element[$url],
                        array(
                            $value_predicate => array(
                                array(
                                    'type' => 'integer',
                                    'value' => intval($dim['label_dimension'])
                                )
                            )
                        )
                    );
                }
                
                // set subPropertyOf
                if( isset($dim['subproperty']) ){
                    $element[$url] = array_merge($element[$url], 
                        array(
                            $subPropertyOf => array(
                                array(
                                    'type' => 'uri',
                                    'value' => $dim['subproperty']
                                )
                            )
                        )
                    );
                }
                
                // set concept
                if( isset($dim['concept']) ){
                    $element[$url] = array_merge($element[$url], 
                        array(
                            $qbconcept => array(
                                array(
                                    'type' => 'uri',
                                    'value' => $dim['concept']
                                )
                            )
                        )
                    );
                }
                
                $elements[] = $element;

                // individuos de las nuevas dimensiones
                
                $query  = "select ".$dim['column']." as label from ".$dim['table'];
                $res = mysql_query( $query, $this->dbhandle );
                while($row = mysql_fetch_array($res))
                {
                    $elabel = $row['label'];
                    $element = array();

                    $eurl = $url."/".urlencode($elabel);

                    $element[$eurl] = array(
                        $type => array(
                            array(
                                'type' => 'uri',
                                'value' => $url
                                )
                            ),
                        $label => array(
                            array(
                                'type' => 'literal',
                                'value' => $elabel
                                )
                            )
                    );
                    $elements[] = $element;                  
                }
            }
            if(isset($dim['measure']) && $dim['measure'] == true)
            {
                if(isset($dim['bytable']) && $dim['bytable'] == true)
                { 
                    $query_me  = "select ".$dim['column']." as label from ".$dim['table'];
                    $res = mysql_query( $query_me, $this->dbhandle );
                    while($row = mysql_fetch_array($res))
                    {
                        $elabel = $row['label'];
                        $element = array();

                        $eurl = $url."/".urlencode($elabel);

                        $element[$eurl] = array(

                            $type => array(
                                array(
                                    'type' => 'uri',
                                    'value' => $property
                                ),
                                array(
                                    'type' => 'uri',
                                    'value' => $qbMeasureProperty
                                )
                            ),
                            $label => array(
                                array(
                                    'type' => 'literal',
                                    'value' => $elabel
                                )
                            ),
                            $subPropertyOf => array(
                                array(
                                    'type' => 'uri',
                                    'value' => $obsValue
                                )
                            ),
                            $range => array(
                                array(
                                    'type' => 'uri',
                                    'value' => $dim['range']
                                )
                            )
                        );
                        $elements[] = $element;                  
                    }   
                }
                else
                {
                     // create incidence
                    $element = array();
                    $element[$url] = array(
                        $type => array(
                            array(
                                'type' => 'uri',
                                'value' => $property
                            ),
                            array(
                                'type' => 'uri',
                                'value' => $qbMeasureProperty
                            )
                        ),
                        $label => array(
                            array(
                                'type' => 'literal',
                                'value' => $dim['label']
                            )
                        ),
                        $subPropertyOf => array(
                            array(
                                'type' => 'uri',
                                'value' => $obsValue
                            )
                        ),
                        $range => array(
                            array(
                                'type' => 'uri',
                                'value' => $dim['range']
                            )
                        )
                    );
                    $elements[] = $element;
                }
            }

            if(isset($dim['dataset']) && $dim['dataset'] == true)
            {
                if(isset($dim['bytable']) && $dim['bytable'] == true)
                { 
                    $query_dataset  = "select ".$dim['column']." as label from ".$dim['table'];
                    $res = mysql_query( $query_dataset, $this->dbhandle );
                    while($row = mysql_fetch_array($res))
                    {
                        $elabel = $row['label'];
                        $element = array();

                        $eurl = $url."/".urlencode($elabel);

                        $element[$eurl] = array( 
                            $type => array(
                                array(
                                    'type' => 'uri',
                                    'value' => $qbDataSet
                                )
                            ),
                            $label => array(
                                array(
                                    'type' => 'literal',
                                    'value' => $elabel
                                )
                            ),
                            $comment => array(
                                array(
                                    'type' => 'literal',
                                    'value' => $elabel
                                )
                            )
                        );
                        $elements[] = $element;
                    }
                }
                else
                {
                    $element[$url] = array(
                        $type => array(
                            array(
                                'type' => 'uri',
                                'value' => $qbDataSet
                            )
                        ),
                        $label => array(
                            array(
                                'type' => 'literal',
                                'value' => $dim['label']
                            )
                        ),
                        $comment => array(
                            array(
                                'type' => 'literal',
                                'value' => $dim['label']
                            )
                        )
                    );
                    $elements[] = $element;
                }
            }
        }

        //$contents="";
        foreach ($elements as $elem) {
            $ontowiki->selectedModel->addMultipleStatements($elem);
            //ob_start();
            //print_r( $elem );
            //$output = ob_get_clean();
            //$contents = $contents.$output."\n";
        }
        $this->logEvent("All dimensions created!");
        //$handle = fopen("/Library/WebServer/Documents/Sites/OntoWiki/extensions/csvimport/logs/dimension1.log", "w");
        //fwrite($handle, $contents);
        //fclose($handle);
    
    }

    protected function db_createDataset(){

        $datastructDefinition = $this->componentConfig->qb->DataStructureDefinition;
        $type = $this->componentConfig->class->type;
        $label = $this->componentConfig->class->label;
        $component = $this->componentConfig->qb->component;
        $qbDataSet = $this->componentConfig->qb->DataSet;
        $comment = $this->componentConfig->class->comment;
        $qbstructure = $this->componentConfig->qb->structure;

        $dimensions = $this->configuration;      

        $elements = array();
        $url_base = $dimensions["uribase"]."DataStructure";

        // create datastructure definition
        $element[$url_base] = array(
            $type => array(
                array(
                    'type' => 'uri',
                    'value' => $datastructDefinition
                )
            )
        );
        
        // append 
        $values = array();
        foreach($dimensions as $url => $dim)
        {
            if($url == "uribase")
                continue;
            if( (isset($dim['measure']) && $dim['measure'] == true) ||
                (isset($dim['attribute']) && $dim['attribute'] == true) ||
                (isset($dim['dimension']) && $dim['dimension'] == true))
            {
                $values[] = array(
                    'type' => 'uri',
                    'value' => $url
                ); 
            }
        }
        
        // merge values
        if( count($values) > 0 ){
            $element[$url_base] = array_merge($element[$url_base],
                array(
                    $component => $values
                )
            );
        }
        $elements[] = $element;
        
        /*$element = array();   
        $url_base_dataset = $dimensions["uribase"]."Dataset";     

        foreach($dimensions as $url => $dim)
        {
            if($url == "uribase")
                continue;
            if( (isset($dim['dataset']) && $dim['dataset'] == true))
            {
                $element[$url_base_dataset] = array(
                    $type => array(
                        array(
                            'type' => 'uri',
                            'value' => $qbDataSet
                        )
                    ),
                    $label => array(
                        array(
                            'type' => 'literal',
                            'value' => $dim['label']
                        )
                    ),
                    $comment => array(
                        array(
                            'type' => 'literal',
                            'value' => $dim['comment']
                        )
                    ),
                    $qbstructure => array(
                        array(
                            'type' => 'uri',
                            'value' => $url_base
                        )
                    )
                );
            }
        }

        $elements[] = $element;*/
        // save to store
        $ontowiki = OntoWiki::getInstance();
        foreach ($elements as $elem) {
            $ontowiki->selectedModel->addMultipleStatements($elem);
        }

        ob_start();
        print_r( $elements );
        $output = ob_get_clean();
        $handle = fopen("/Library/WebServer/Documents/Sites/OntoWiki/extensions/csvimport/logs/dataset1.log", "w");
        fwrite($handle, $output);
        fclose($handle);
    }

    protected function getArrayComponents($dimensions,$component)
    {
        $urls = array();
        $table_to = array();
        $column_to = array();
        $column_from = array();
        $column_value = array();

        foreach($dimensions as $url => $dim)
        {
            if($url == "uribase")
                continue;

            if( isset($dim['fact']) && $dim['fact'] == true)
            {
                if( isset($dim[$component]) )
                {
                    foreach($dim[$component] as $eurl => $elem)
                    {
                        array_push($urls, $eurl);
                        array_push($table_to,$elem['to_table']);
                        array_push($column_to, $elem['to']);
                        array_push($column_from, $elem['from']);
                        array_push($column_value, $elem['column']);
                    }
                }
                $table_from = $dim['table'];
                $value =$dim['column'];
            }
        }

        $arrays = array();
        $arrays['urls'] = $urls;
        $arrays['table_to'] = $table_to;
        $arrays['column_to'] = $column_to;
        $arrays['column_from'] = $column_from;
        $arrays['column_value'] = $column_value;
        return $arrays;
    }

    protected function sqlbuilder($table_to, $column_from, $column_to, $column_value,$tipo)
    {
        $tables = "";
        $columns = "";
        $where = "";
        for($cont=0; $cont<count($table_to); $cont++)
        {
            if($cont!=0)
                $where = $where." and ";
            $tables = $tables.$table_to[$cont]." , ";
            $columns = $columns.$table_to[$cont].".".$column_value[$cont]." as label".$tipo.($cont+2).", ";
            $where = $where." t1.".$column_from[$cont]."= ".$table_to[$cont].".".$column_to[$cont];

        }
        return $tables."@".$columns."@".$where;
    }

    protected function db_saveData() {

        //$contents = "";

        /**********************************************************************************/
        /*******************  conexion con BD & set $this->dbhandle ***********************/
        /**********************************************************************************/

        $type = $this->componentConfig->class->type;
        $label = $this->componentConfig->class->label;
        $comment = $this->componentConfig->class->comment;
        $dataset = $this->componentConfig->qb->dataset;
        $qbObservation = $this->componentConfig->qb->Observation;

        $this->logEvent("Saving data to knowledge base..");
        $ontowiki = OntoWiki::getInstance();
        $dimensions = $this->configuration;
        
        // dimensions array
        $dims = array();

        // item url base
        //$url_base = $this->componentConfig->local->base . "/".hash("md5", serialize($this->parsedFile));
        $url_base = $dimensions["uribase"]."item/";
        // count
        $count = 0;
        $dimensions_in = NULL;
        $attributes_in = NULL;
        $measures_in = NULL;
        $datasets_in = NULL;
        
        $url_measure = NULL;
        $measure_tabela = NULL;

        $url_attribute = NULL;
        $uri_attribute = NULL;
        $attribute_tabela = NULL;

        $url_dataset = NULL;
        $dataset_tabela = NULL;


        foreach($dimensions as $url => $dim)
        {
            if($url == "uribase")
                continue;

            if( isset($dim['fact']) && $dim['fact'] == true)
            {
                if( isset($dim['dimensions']) )
                {
                    $dimensions_in = $this->getArrayComponents($dimensions, "dimensions");
                }
                if( isset($dim['attributes']) )
                {
                    $attributes_in = $this->getArrayComponents($dimensions, "attributes");
                }
                if( isset($dim['measures']) )
                {
                    $measures_in = $this->getArrayComponents($dimensions, "measures");
                }
                if( isset($dim['datasets']) )
                {
                    $datasets_in = $this->getArrayComponents($dimensions, "datasets");
                }
                $table_from = $dim['table'];
                $value =$dim['column'];
            }
            if(isset($dim['measure']) && $dim['measure'] == true)
            {
                $url_measure = $url;
                if(isset($dim['bytable']))
                {
                    $measure_tabela = 1;
                }
            }
            if(isset($dim['attribute']) && $dim['attribute'] == true)
            {
                $url_attribute = $url;
                $uri_attribute = $dim['uri'];
                if(isset($dim['bytable']))
                {
                    $attribute_tabela = 1;
                }
            }
            if(isset($dim['dataset']) && $dim['dataset'] == true)
            {
                $url_dataset = $url;
                if(isset($dim['bytable']))
                {
                    $dataset_tabela = 1;
                }
            }
        }

        $urls = array();
        $table_to = array();
        $column_to = array();
        $column_from = array();
        $column_value = array();

        $statementsDim = NULL;
        $statementsAtt = NULL;
        $statementsMea = NULL;
        $statementsData = NULL;


        if(isset($dimensions_in))
        {
            $urls_Dim =  array_merge_recursive($urls, $dimensions_in['urls']);
            $table_to_Dim = $dimensions_in['table_to'];
            $column_to = array_merge_recursive($column_to, $dimensions_in['column_to']);
            $column_from = array_merge_recursive($column_from, $dimensions_in['column_from']);
            $column_value = array_merge_recursive($column_value, $dimensions_in['column_value']);
            $statementsDim = $this->sqlbuilder($dimensions_in['table_to'], $dimensions_in['column_from'], $dimensions_in['column_to'], $dimensions_in['column_value'],"dim");
        }
        if(isset($attributes_in))
        {
            $urls = array_merge_recursive($urls, $attributes_in['urls']);
            $table_to = array_merge_recursive($table_to, $attributes_in['table_to']);
            $column_to = array_merge_recursive($column_to, $attributes_in['column_to']);
            $column_from = array_merge_recursive($column_from, $attributes_in['column_from']);
            $column_value = array_merge_recursive($column_value, $attributes_in['column_value']);
            $statementsAtt = $this->sqlbuilder($attributes_in['table_to'], $attributes_in['column_from'], $attributes_in['column_to'], $attributes_in['column_value'],"att");
        }
        if(isset($measures_in))
        {
            $urls = array_merge_recursive($urls, $measures_in['urls']);
            $table_to = array_merge_recursive($table_to, $measures_in['table_to']); 
            $column_to = array_merge_recursive($column_to, $measures_in['column_to']);
            $column_from = array_merge_recursive($column_from, $measures_in['column_from']);
            $column_value = array_merge_recursive($column_value, $measures_in['column_value']);
            $statementsMea = $this->sqlbuilder($measures_in['table_to'], $measures_in['column_from'], $measures_in['column_to'], $measures_in['column_value'],"mea");
        }
        if(isset($measures_in))
        {
            $urls = array_merge_recursive($urls, $datasets_in['urls']);
            $table_to = array_merge_recursive($table_to, $datasets_in['table_to']); 
            $column_to = array_merge_recursive($column_to, $datasets_in['column_to']);
            $column_from = array_merge_recursive($column_from, $datasets_in['column_from']);
            $column_value = array_merge_recursive($column_value, $datasets_in['column_value']);
            $statementsData = $this->sqlbuilder($datasets_in['table_to'], $datasets_in['column_from'], $datasets_in['column_to'], $datasets_in['column_value'],"data");
        }

        $tables = "";
        $columns = "";
        $where = "";

        if(isset($statementsDim) && $statementsDim!= "@@")
        {
            $astatementsDim = explode("@",$statementsDim);
            $tables = $tables.$astatementsDim[0];
            $columns = $columns.$astatementsDim[1];
            $where = $where.$astatementsDim[2];
        }
        if(isset($statementsAtt) && $statementsAtt !="@@")
        {
            $astatementsAtt = explode("@",$statementsAtt);
            $tables = $tables.$astatementsAtt[0];
            $columns = $columns.$astatementsAtt[1];
            if($where != "")
                $where = $where." and ";
            $where = $where.$astatementsAtt[2];
        }
        if(isset($statementsMea) && $statementsMea != "@@")
        {
            $astatementsMea = explode("@",$statementsMea);
            $tables = $tables.$astatementsMea[0];
            $columns = $columns.$astatementsMea[1];
            if($where != "")
                $where = $where." and ";
            $where = $where.$astatementsMea[2];
        }
        if(isset($statementsData) && $statementsData != "@@")
        {
            $astatementsData = explode("@",$statementsData);
            $tables = $tables.$astatementsData[0];
            $columns = $columns.$astatementsData[1];
            if($where != "")
                $where = $where." and ";
            $where = $where.$astatementsData[2];
        }
        
        $query  = "select ".$columns." t1.".$value." as value from ".$tables.$table_from." as t1 where ".$where;

        //echo "<h1>".$query."</h1>";

        $res = mysql_query( $query, $this->dbhandle );

        while($row = mysql_fetch_array($res))
        {
            $itemDims = array();

            $cell = $row['value'];
            for($cont=0; $cont<count($table_to_Dim); $cont++)
            {
                $index = "labeldim".($cont+2);
                $elabel = $row[$index];
                $eurl = $urls_Dim[$cont]."/".urlencode($elabel);

                $itemDims[$urls_Dim[$cont]][] = array(
                                            'type' => 'uri',
                                            'value' => $eurl
                                            );
            }
            
            $md5 = hash("md5", serialize($itemDims).$cell);
            $eurl = $url_base.$md5;
            if(count($itemDims) > 0)
            {
                if(isset($measure_tabela))
                {
                    $url_measure_novo = $url_measure."/".urlencode($row["labelmea2"]);
                }
                if(isset($url_measure) && !isset($measure_tabela))
                {
                    $url_measure_novo = $url_measure;
                }
                if(!isset($url_measure))
                {
                   $url_measure_novo = "http://example.com/incidence";
                }

                if(isset($attribute_tabela))
                {
                    $url_attribute_novo = $url_attribute."/".urlencode($row["labelatt2"]);
                }
                if(isset($url_attribute) && !isset($attribute_tabela))
                {
                    $url_attribute_novo = $url_attribute;
                }
                if(isset($url_attribute))
                {
                    $attributes = array();
                    $attributes[$uri_attribute] = array(
                                                        array(
                                                            'type' => 'uri',
                                                            'value' => $url_attribute_novo
                                                        )
                                                    );
                }

                if(isset($dataset_tabela))
                {
                    $url_dataset_novo = $url_dataset."/".urlencode($row["labeldata2"]);
                }
                if(isset($url_dataset) && !isset($dataset_tabela) )
                {
                    $url_dataset_novo = $url_dataset;
                }
                if(isset($url_dataset))
                {
                    $aDataset = array();
                    $aDataset[$this->componentConfig->qb->dataset] = array(
                                                        array(
                                                            'type' => 'uri',
                                                            'value' => $url_dataset_novo
                                                        )
                                                    );
                }

                $element = array();
                $element[$eurl] = array_merge(
                                $itemDims,
                                array(
                                    $url_measure_novo => array(
                                        array(
                                            'type' => 'literal',
                                            'value' => floatval( $cell )
                                        )
                                    ),
                                    $type => array(
                                        array(
                                            'type' => 'uri',
                                            'value' => $qbObservation
                                        )
                                    )
                                )
                            );
            }

            $element[$eurl] = array_merge($element[$eurl],$attributes);
            $element[$eurl] = array_merge($element[$eurl],$aDataset); 
            $count++;
            if($count%1000 == 0){
                $this->logEvent("Total triples saved: ".$count.". Still working..");
            } 
            $ontowiki->selectedModel->addMultipleStatements($element);
            //ob_start();
            //print_r( $element );
            //$output = ob_get_clean();
            //$contents = $contents.$output."\n";
        }

        //$handle =  fopen("/Library/WebServer/Documents/Sites/OntoWiki/extensions/csvimport/logs/data1.log", "w");
        //fwrite($handle, $contents);
        //fclose($handle);

        $this->logEvent("Done!");
    }

}
