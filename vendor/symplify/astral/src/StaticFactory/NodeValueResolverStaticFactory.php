<?php

declare (strict_types=1);
namespace RectorPrefix20211016\Symplify\Astral\StaticFactory;

use PhpParser\NodeFinder;
use RectorPrefix20211016\Symplify\Astral\NodeFinder\SimpleNodeFinder;
use RectorPrefix20211016\Symplify\Astral\NodeValue\NodeValueResolver;
use RectorPrefix20211016\Symplify\PackageBuilder\Php\TypeChecker;
/**
 * @api
 */
final class NodeValueResolverStaticFactory
{
    public static function create() : \RectorPrefix20211016\Symplify\Astral\NodeValue\NodeValueResolver
    {
        $simpleNameResolver = \RectorPrefix20211016\Symplify\Astral\StaticFactory\SimpleNameResolverStaticFactory::create();
        $simpleNodeFinder = new \RectorPrefix20211016\Symplify\Astral\NodeFinder\SimpleNodeFinder(new \RectorPrefix20211016\Symplify\PackageBuilder\Php\TypeChecker(), new \PhpParser\NodeFinder());
        return new \RectorPrefix20211016\Symplify\Astral\NodeValue\NodeValueResolver($simpleNameResolver, new \RectorPrefix20211016\Symplify\PackageBuilder\Php\TypeChecker(), $simpleNodeFinder);
    }
}
