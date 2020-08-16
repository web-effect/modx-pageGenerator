<?php
if(!class_exists('modxPackagesResolver')){
    class modxPackagesResolver extends modxScriptVehicleResolver{
        public $packages=[];
        
        
        public function __construct(&$modx,$options,&$object){
            parent::__construct($modx,$options,$object);
            $this->packages=array_merge($this->packages,$this->config['packages']?:[]);
        }

        /********************************************************/
        public function install(){
            $this->installAll();
        }
        public function upgrade(){
            $this->installAll();
        }
        public function uninstall(){
            
        }
        /********************************************************/
        
        public function installAll(){
            foreach ($this->packages as $name => $data) {
                if(!is_array($data))$data=['version' => $data];
                $this->installPackage($name,$data);
            }
        }
        
        public function isInstalled($packageName,$packageVersion){
            $installed = $this->modx->getIterator('transport.modTransportPackage', ['package_name' => $packageName]);
            foreach ($installed as $package) {
                if ($package->compareVersion($packageVersion,'<='))return true;
            }
            return false;
        }
        
        public function installPackage($packageName, $options = []){
            $this->addMessage("Trying to install <b>{$name}</b>. Please wait...");
            if($this->isInstalled($packageName,$options['version'])){
                $this->addMessage("Package <b>{".$packageName."}</b> already installed.");
                return true;
            }
            
            if(!empty($options['service_url'])) {
                $provider = $this->modx->getObject('transport.modTransportProvider', [
                    'service_url:LIKE' => '%' . $options['service_url'] . '%',
                ]);
                if(empty($provider)){
                    $this->addError("Package <b>{".$packageName."}</b> not installed because provider for service <b>{".$options['service_url']."}</b> not registered.");
                    return false;
                }
            }
            $provider = $provider?:$this->modx->getObject('transport.modTransportProvider', 1);
            
            $this->modx->getVersionData();
            $productVersion = $this->modx->version['code_name'] . '-' . $this->modx->version['full_version'];
            $response = $provider->request('package', 'GET', [
                'supports' => $productVersion,
                'query' => $packageName,
            ]);
            if(empty($response)){
                $this->addError("Could not find <b>{$packageName}</b> in MODX repository");
                return false;
            }
            
            $foundPackages = simplexml_load_string($response->response);
            $foundedPackage = false;
            foreach ($foundPackages as &$foundPackage) {
                if($foundPackage->name != $packageName)continue;
                $foundedPackage=$foundPackage;
                break;
            }
            if(!$foundedPackage){
                $this->addError("Could not find <b>{$packageName}</b> in MODX repository by name");
                return false;
            }
            
            $sig = explode('-', $foundedPackage->signature);
            $versionSignature = explode('.', $sig[1]);
            $url = $foundedPackage->location;
            if(!$this->downloadPackage($url, $this->modx->getOption('core_path') . 'packages/' . $foundedPackage->signature . '.transport.zip'))return false;

            // Add in the package as an object so it can be upgraded
            /** @var modTransportPackage $package */
            $package = $this->modx->newObject('transport.modTransportPackage');
            $package->set('signature', $foundedPackage->signature);
            /** @noinspection PhpUndefinedFieldInspection */
            $package->fromArray([
                'created' => date('Y-m-d h:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => $provider->get('id'),
                'source' => $foundedPackage->signature . '.transport.zip',
                'package_name' => $packageName,
                'version_major' => $versionSignature[0],
                'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
            ]);

            if (!empty($sig[2])) {
                $r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($r) && !empty($r)) {
                    $package->set('release', $r[0]);
                    $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
                } else {
                    $package->set('release', $sig[2]);
                }
            }

            if ($package->save() && $package->install()) {
                $this->addMessage("<b>{$packageName}</b> was successfully installed");
            } else {
                $this->addError("Could not save package <b>{$packageName}</b>");
            }

            return $this->hasErrors();
        }
        
        public function downloadPackage($src, $dst) {
            if(ini_get('allow_url_fopen'))$file = @file_get_contents($src);
            else{
                if(!function_exists('curl_init')){
                    $this->addError('Could not download package because cURL extension not existed');
                    return false;
                }
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $src);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                $safeMode = @ini_get('safe_mode');
                $openBasedir = @ini_get('open_basedir');
                if (empty($safeMode) && empty($openBasedir))curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                $file = curl_exec($ch);
                curl_close($ch);
            }
            file_put_contents($dst, $file);
            return file_exists($dst);
        }
    }
}

$packagesResolver=new modxPackagesResolver($transport->xpdo,$options,$object);
$packagesResolver->run();

return !$packagesResolver->hasErrors();
