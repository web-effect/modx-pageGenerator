<?php
if(!class_exists('modxMIGXResolver')){
    class modxMIGXResolver extends modxScriptVehicleResolver{
        public $migx=false;
        
        public function __construct(&$modx,$options,&$object){
            parent::__construct($modx,$options,$object);
            
            $miTVCorePath = $this->modx->getOption('migx.core_path',null,$modx->getOption('core_path').'components/migx/');
            require_once $miTVCorePath.'model/migx/migx.class.php';
            $this->modx->migx = new Migx($this->modx);
            $this->migx=&$this->modx->migx;
            $this->migx->config['configs'] = 'migxconfigs';
            $this->migx->loadConfigs();
        }
        

        /********************************************************/
        public function install(){
            $this->updateConfigs();
        }
        public function upgrade(){
            $this->updateConfigs();
        }
        public function uninstall(){
            
        }
        /********************************************************/
        
        public function updateConfigs(){
            $configs_file=$this->config['corePath'].'migx.configs.json';
            if(!file_exists($configs_file)){
                $this->addMessage("migx.configs.json not existed");
                return false;
            }
            $configs=json_decode(file_get_contents($configs_file),true);
            if(!is_array($configs)){
                $this->addError("migx.configs.json is empty or wrong json");
                return false;
            }
            
            foreach($configs as $name=>$config){
                $this->updateConfig($name,$config);
            }
        }
        public function updateConfig($name,$config){
            $this->addMessage("Update MIGX config <b>{$name}</b>");
            $config['name']=$name;
            $scriptProperties=[
                'action'=>'mgr/migxdb/update',
                'data'=>json_encode([
                    "jsonexport"=>json_encode($config)
                ]),
                'configs'=>'migxconfigs',
                'resource_id'=>'',
                'co_id'=>'',
                'object_id'=>'new',
                'tv_id'=>2,
                'wctx'=>'mgr',
                'tempParams'=>'export_import',
                "HTTP_MODAUTH"=>$_SESSION["modx.mgr.user.token"]
            ];
            $existed=$this->modx->getObject('migxConfig',['name'=>$name]);
            if($existed)$scriptProperties['object_id']=$existed->id;
            
            
            
            
            if (isset($scriptProperties['data'])) {
                $scriptProperties = array_merge($this->modx->fromJson($scriptProperties['data']),$scriptProperties);
            }
            $this->migx->loadConfigs();
            $tabs = $this->migx->getTabs();
            $fieldid = 0;
            $postvalues = array();
            foreach ($scriptProperties as $field => $value) {
                $fieldid++;
                if (is_array($value)) {
                    $featureInsert = array();
                    while (list($featureValue, $featureItem) = each($value)) {
                        $featureInsert[count($featureInsert)] = $featureItem;
                    }
                    $value = implode('||', $featureInsert);
                }
                if ($field != 'configs') {
                    $field = explode('.', $field);
                    if (count($field) > 1) {
                        //extended field (json-array)
                        $postvalues[$field[0]][$field[1]] = $value;
                    } else {
                        $postvalues[$field[0]] = $value;
                    }
                }
            }
            if (isset($postvalues['jsonexport'])) {
                $postvalues = $this->migx->importconfig($this->modx->fromJson($postvalues['jsonexport']));
            }
            if ($scriptProperties['object_id'] == 'new') {
                $object = $this->modx->newObject('migxConfig');
                $tempvalues['createdon'] = strftime('%Y-%m-%d %H:%M:%S');
                $postvalues['createdby'] = $this->modx->user->get('id');
            } else {
                $object = $this->modx->getObject('migxConfig', $scriptProperties['object_id']);
                if (empty($object))
                    return $this->modx->error->failure($this->modx->lexicon('quip.thread_err_nf'));
                $postvalues['editedon'] = strftime('%Y-%m-%d %H:%M:%S');
                $postvalues['editedby'] = $this->modx->user->get('id');
                $tempvalues['createdon'] = $object->get('createdon');
                $tempvalues['publishedon'] = $object->get('publishedon');
            }
            
            $newtabs = array();
            if (isset($postvalues['formtabs'])) {
                $formtabs = $this->modx->fromJson($postvalues['formtabs']);
                if (is_array($formtabs) && count($formtabs) > 0) {
                    foreach ($formtabs as $tab) {
                        $fields = is_array($tab['fields']) ? $tab['fields'] : $this->modx->fromJson($tab['fields']);
                        $tab['fields'] = $fields;
                        $newtabs[] = $tab;
                    }
                    $postvalues['formtabs'] = $this->modx->toJson($newtabs);
                }
    
            }
            
            if (isset($postvalues['formlayouts'])) {
                $newtabs = array();
                $formlayouts = $this->modx->fromJson($postvalues['formlayouts']);
                if (is_array($formlayouts) && count($formlayouts) > 0) {
                    $fields = array();
                    $tab = false;
                    $layout_id = 0; 
                    $column_id = 0;
                    $columnwidth = 0;
                    $columncaption = '';
                    $columnstyle = '';
                    $columnminwidth = '';                          
                    $layoutcaption = '';
                    $layoutstyle = '';                   
                    foreach ($formlayouts as $formlayout) {
                        $type = $this->modx->getOption('MIGXtype', $formlayout, '');
                        switch ($type) {
                            case 'formtab':
                                if ($tab) {
                                    //next tab
                                    $tab['fields'] = $fields;
                                    $newtabs[] = $tab;
                                    $fields = array();
                                }
                                $tab = $formlayout;
                                $layout_id = 0; 
                                $column_id = 0;
                                $columnwidth = 0; 
                                $columncaption = '';
                                $columnstyle = '';
                                $columnminwidth = '';                          
                                $layoutcaption = '';
                                $layoutstyle = '';                                                         
                                break;
                            case 'layout':
                                $layout_id++;
                                $column_id = 0;
                                $columnwidth = 0;
                                $columncaption = '';
                                $columnstyle = '';
                                $columnminwidth = '';                          
                                $layoutcaption = $this->modx->getOption('MIGXlayoutcaption', $formlayout, '');
                                $layoutstyle = $this->modx->getOption('MIGXlayoutstyle', $formlayout, '');                            
                                break;
                            case 'column':
                                $column_id++;
                                $columnwidth = $this->modx->getOption('field', $formlayout, '');
                                $columnminwidth = $this->modx->getOption('MIGXcolumnminwidth', $formlayout, '');
                                $columncaption = $this->modx->getOption('MIGXcolumncaption', $formlayout, '');
                                $columnstyle = $this->modx->getOption('MIGXcolumnstyle', $formlayout, '');
                                break;
                            case 'field':
                                if (!$tab) {
                                    $tab = array();
                                    $tab['caption'] = 'undefined';
                                }
                                $field = $formlayout;
                                $field['MIGXlayoutid'] = $layout_id;
                                $field['MIGXcolumnid'] = $column_id;
                                $field['MIGXcolumnwidth'] = $columnwidth;
                                $field['MIGXcolumnminwidth'] = $columnminwidth;
                                $field['MIGXcolumnstyle'] = $columnstyle;
                                $field['MIGXcolumncaption'] = $columncaption;
                                $field['MIGXlayoutstyle'] = $layoutstyle;
                                $field['MIGXlayoutcaption'] = $layoutcaption;                            
                                $fields[] = $field;
                                break;
                        }
    
    
                    }
                    if ($tab) {
                        //last tab
                        $tab['fields'] = $fields;
                        $newtabs[] = $tab;
                        $fields = array();
                    }
                    $postvalues['formtabs'] = $this->modx->toJson($newtabs);
                }
    
            }
    
            $newcolumns = array();
            if (isset($postvalues['columns'])) {
                $columns = $this->modx->fromJson($postvalues['columns']);
                if (is_array($columns) && count($columns) > 0) {
                    foreach ($columns as $column) {
                        if (isset($column['customrenderer']) && !empty($column['customrenderer'])) {
                            $column['renderer'] = $column['customrenderer'];
    
                        }
                        $newcolumns[] = $column;
                    }
                    $postvalues['columns'] = $this->modx->toJson($newcolumns);
                }
    
            }
    
            //handle published
            $postvalues['published'] = isset($postvalues['published']) ? $postvalues['published'] : '1';
            if (isset($tempvalues['createdon']) && empty($postvalues['ow_createdon'])) {
                $postvalues['createdon'] = $tempvalues['createdon'];
            }
            if (isset($tempvalues['publishedon']) && empty($postvalues['ow_publishedon'])) {
                $postvalues['publishedon'] = $tempvalues['publishedon'];
            }
    
            $object->fromArray($postvalues);
            
            if ($object->save() == false){
                $this->addError("Could not save MIGX config <b>{$config['name']}</b>");
                return false;
            }
        }
    }
}

$migxResolver=new modxMIGXResolver($transport->xpdo,$options,$object);
$migxResolver->run();
return !$migxResolver->hasErrors();
