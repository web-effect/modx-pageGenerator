<?php

namespace PGenerator\Events;

class OnDocFormSave extends Event
{
    public function run()
    {
        $resource=$this->scriptProperties['resource'];
        
        if($this->cmp->isGenerator($resource->id)){
        	//$this->cmp->log('isGenerator');
        	$disabled=$resource->getTVValue('generator_disabled');
			if($disabled)return;
			$iterator = $this->cmp->getGeneratedRootsIterator($resource->id);
			$iterator->rewind();
			if(!$iterator->valid())return;
			foreach($iterator as $root){
				//$this->modx->log(1,$root->pagetitle);
				$this->cmp->generatePage($resource,$root);
			}
			return;
		}
		if($this->cmp->isGenerated($resource->id)){
			//$this->cmp->log('isGenerated');
			$generator = $this->cmp->getGeneratedGenerator($resource);
			if(!$generator)return;
			$disabled=$generator->getTVValue('generator_disabled');
			if($disabled)return;
			$root= $this->cmp->getGeneratedRoot($resource);
			if(!$root)return;
			$this->cmp->generatePage($generator,$root);
			return;
		}
		if($this->cmp->isGeneratedRoot($resource->id)){
			//$this->cmp->log('isGeneratedRoot');
			$iterator = $this->cmp->getGeneratorsIteratorByGeneratedRootsParent($resource->parent);
			foreach($iterator as $generator){
				$this->cmp->generatePage($generator,$resource);
			}
			return;
		}
    }

}
