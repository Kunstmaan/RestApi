<?php

/*
 * This file is part of the KunstmaanBundlesCMS package.
 *
 * (c) Kunstmaan <https://github.com/Kunstmaan/KunstmaanBundlesCMS/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kunstmaan\Rest\CoreBundle;

use Kunstmaan\Rest\CoreBundle\DependencyInjection\Compiler\NelmioDefinitionsCompilerPass;
use Kunstmaan\Rest\CoreBundle\DependencyInjection\Compiler\TransformerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class KunstmaanRestCoreBundle
 */
class KunstmaanRestCoreBundle extends Bundle
{
    /**
     * @inheritdoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new TransformerCompilerPass());
        $container->addCompilerPass(new NelmioDefinitionsCompilerPass());
    }
}
