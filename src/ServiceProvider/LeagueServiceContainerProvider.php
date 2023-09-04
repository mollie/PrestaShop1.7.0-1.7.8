<?php

namespace Mollie\ServiceProvider;

use League\Container\Container;
use League\Container\ReflectionContainer;

class LeagueServiceContainerProvider implements ServiceContainerProviderInterface
{
    private $extendedServices = [];

    /** {@inheritDoc} */
    public function getService(string $serviceName)
    {
        $container = new Container();

        $container->delegate(new ReflectionContainer());

        (new BaseServiceProvider($this->extendedServices))->register($container);

        return $container->get($serviceName);
    }

    public function extend(string $id, string $concrete = null)
    {
        $this->extendedServices[$id] = $concrete;

        return $this;
    }
}
