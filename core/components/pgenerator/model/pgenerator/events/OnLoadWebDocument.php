<?php

namespace PGenerator\Events;

class OnLoadWebDocument extends Event
{
    public function run()
    {
        if($this->cmp->isGenerated($this->modx->resource->id)){
			$generator = $this->cmp->getGeneratedGenerator($this->modx->resource);
			$root= $this->cmp->getGeneratedRoot($this->modx->resource);
			$this->modx->resource->set('generator',$generator->toArray());
			$this->modx->resource->set('root',$root->toArray());
			$this->modx->resource->set('generator_id',$generator->id);
			$this->modx->resource->set('root_id',$root->id);
		}
    }

}
