<?php
return array(
    'factories' => array(
        //doctrine.entitymanager.orm_default
        'rcsbase_doctrine_descriminatorlistener' => function ($sl) {

                $Configuration = $sl->get('doctrine.configuration.orm_default');
                return new RCSBase\Doctrine\DiscriminatorListener( $Configuration ) ;
            },
    )
);