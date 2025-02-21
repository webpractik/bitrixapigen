<?php

use Bitrix\Main\DI\ServiceLocator;

$serviceLocator = ServiceLocator::getInstance();

$serviceValue = [];

/** по каждому эндпоинту */
if ($serviceLocator->has('webpractik.bitrixgen.addPet')) {
    if (!in_array(class_implements($serviceLocator->get('webpractik.bitrixgen.addPet')), "\Webpractik\Bitrixgen\Endpoint\IAddPet")) {
        /** если класс зарегистрирован, но не импл интерфейс - перерегистрируем на заглушку */
        $serviceValue['webpractik.bitrixgen.addPet'] = [
            'className' => \Webpractik\Bitrixgen\Endpoint\AddPet::class
        ];
    }
}
/** если класс не регистрировали - перерегистрируем на заглушку */
if (!$serviceLocator->has('webpractik.bitrixgen.addPet')) {
    $serviceValue['webpractik.bitrixgen.addPet'] = [
        'className' => \Webpractik\Bitrixgen\Endpoint\AddPet::class
    ];
}


return [
    'services' => [
        'value' => $serviceValue
    ],
];
