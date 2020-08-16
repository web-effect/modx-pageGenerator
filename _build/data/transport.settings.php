<?php


$data['modSystemSetting']=[
    [
        'fields'=>[
            'key'=>$config['component']['namespace'].'.generated.roots',
            'value'=>'',
            'xtype'=>'textfield',
            'namespace'=>$config['component']['namespace'],
            'area'=>$config['component']['namespace'].'.generated'
        ],
        'options'=>$config['data_options']['modSystemSetting']
    ],
    [
        'fields'=>[
            'key'=>$config['component']['namespace'].'.generator.roots',
            'value'=>'',
            'xtype'=>'textfield',
            'namespace'=>$config['component']['namespace'],
            'area'=>$config['component']['namespace'].'.generator'
        ],
        'options'=>$config['data_options']['modSystemSetting']
    ],
];