<?php

declare (strict_types=1);
namespace Rector\DogFood\Rector\Closure;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Expression;
use Rector\Core\Configuration\Option;
use Rector\Core\Rector\AbstractRector;
use Rector\DogFood\NodeAnalyzer\ContainerConfiguratorCallAnalyzer;
use Rector\DogFood\NodeManipulator\EmptyAssignRemover;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
/**
 * @see \Rector\Tests\DogFood\Rector\Closure\UpgradeRectorConfigRector\UpgradeRectorConfigRectorTest
 */
final class UpgradeRectorConfigRector extends \Rector\Core\Rector\AbstractRector
{
    /**
     * @var array<string, string>
     */
    private const PARAMETER_NAME_TO_METHOD_CALL_MAP = [\Rector\Core\Configuration\Option::AUTOLOAD_PATHS => 'autoloadPaths', \Rector\Core\Configuration\Option::BOOTSTRAP_FILES => 'bootstrapFiles', \Rector\Core\Configuration\Option::AUTO_IMPORT_NAMES => 'importNames', \Rector\Core\Configuration\Option::PARALLEL => 'parallel', \Rector\Core\Configuration\Option::PHPSTAN_FOR_RECTOR_PATH => 'phpstanConfig', \Rector\Core\Configuration\Option::PHP_VERSION_FEATURES => 'phpVersion'];
    /**
     * @var string
     */
    private const RECTOR_CONFIG_VARIABLE = 'rectorConfig';
    /**
     * @var string
     */
    private const RECTOR_CONFIG_CLASS = 'Rector\\Config\\RectorConfig';
    /**
     * @var string
     */
    private const PARAMETERS_VARIABLE = 'parameters';
    /**
     * @readonly
     * @var \Rector\DogFood\NodeAnalyzer\ContainerConfiguratorCallAnalyzer
     */
    private $containerConfiguratorCallAnalyzer;
    /**
     * @readonly
     * @var \Rector\DogFood\NodeManipulator\EmptyAssignRemover
     */
    private $emptyAssignRemover;
    public function __construct(\Rector\DogFood\NodeAnalyzer\ContainerConfiguratorCallAnalyzer $containerConfiguratorCallAnalyzer, \Rector\DogFood\NodeManipulator\EmptyAssignRemover $emptyAssignRemover)
    {
        $this->containerConfiguratorCallAnalyzer = $containerConfiguratorCallAnalyzer;
        $this->emptyAssignRemover = $emptyAssignRemover;
    }
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Upgrade rector.php config to use of RectorConfig', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample(<<<'CODE_SAMPLE'
use Rector\Core\Configuration\Option;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::PARALLEL, true);
    $parameters->set(Option::AUTO_IMPORT_NAMES, true);

    $services = $containerConfigurator->services();
    $services->set(TypedPropertyRector::class);
};
CODE_SAMPLE
, <<<'CODE_SAMPLE'
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->importNames();

    $rectorConfig->rule(TypedPropertyRector::class);
};
CODE_SAMPLE
)]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Expr\Closure::class];
    }
    /**
     * @param Closure $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        $params = $node->getParams();
        if (\count($params) !== 1) {
            return null;
        }
        $onlyParam = $params[0];
        $paramType = $onlyParam->type;
        if (!$paramType instanceof \PhpParser\Node\Name) {
            return null;
        }
        if (!$this->isNames($paramType, ['Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\ContainerConfigurator', self::RECTOR_CONFIG_CLASS])) {
            return null;
        }
        $this->updateClosureParam($onlyParam);
        // 1. change import of sets to single sets() method call
        $this->refactorSetImportMethodCalls($node);
        $this->traverseNodesWithCallable($node->getStmts(), function (\PhpParser\Node $node) : ?MethodCall {
            // 2. call on rule
            if ($node instanceof \PhpParser\Node\Expr\MethodCall) {
                if ($this->containerConfiguratorCallAnalyzer->isMethodCallWithServicesSetConfiguredRectorRule($node)) {
                    return $this->refactorConfigureRuleMethodCall($node);
                }
                // look for "$services->set(SomeRector::Class)"
                if ($this->containerConfiguratorCallAnalyzer->isMethodCallWithServicesSetRectorRule($node)) {
                    $node->var = new \PhpParser\Node\Expr\Variable(self::RECTOR_CONFIG_VARIABLE);
                    $node->name = new \PhpParser\Node\Identifier('rule');
                    return $node;
                }
                if ($this->containerConfiguratorCallAnalyzer->isMethodCallNamed($node, self::PARAMETERS_VARIABLE, 'set')) {
                    return $this->refactorParameterName($node);
                }
            }
            return null;
        });
        $this->emptyAssignRemover->removeFromClosure($node);
        return $node;
    }
    public function updateClosureParam(\PhpParser\Node\Param $param) : void
    {
        if (!$param->type instanceof \PhpParser\Node\Name) {
            return;
        }
        // update closure params
        if (!$this->nodeNameResolver->isName($param->type, self::RECTOR_CONFIG_CLASS)) {
            $param->type = new \PhpParser\Node\Name\FullyQualified(self::RECTOR_CONFIG_CLASS);
        }
        if (!$this->nodeNameResolver->isName($param->var, self::RECTOR_CONFIG_VARIABLE)) {
            $param->var = new \PhpParser\Node\Expr\Variable(self::RECTOR_CONFIG_VARIABLE);
        }
    }
    /**
     * @param Arg[] $args
     */
    private function isOptionWithTrue(array $args, string $optionName) : bool
    {
        if (!$this->valueResolver->isValue($args[0]->value, $optionName)) {
            return \false;
        }
        return $this->valueResolver->isTrue($args[1]->value);
    }
    /**
     * @return \PhpParser\Node\Expr\MethodCall|null
     */
    private function refactorConfigureRuleMethodCall(\PhpParser\Node\Expr\MethodCall $methodCall)
    {
        $caller = $methodCall->var;
        if (!$caller instanceof \PhpParser\Node\Expr\MethodCall) {
            return null;
        }
        if (!$this->containerConfiguratorCallAnalyzer->isMethodCallWithServicesSetRectorRule($caller)) {
            return null;
        }
        $methodCall->var = new \PhpParser\Node\Expr\Variable(self::RECTOR_CONFIG_VARIABLE);
        $methodCall->name = new \PhpParser\Node\Identifier('ruleWithConfiguration');
        $methodCall->args = \array_merge($caller->getArgs(), $methodCall->getArgs());
        return $methodCall;
    }
    private function refactorParameterName(\PhpParser\Node\Expr\MethodCall $methodCall) : ?\PhpParser\Node\Expr\MethodCall
    {
        foreach (self::PARAMETER_NAME_TO_METHOD_CALL_MAP as $parameterName => $methodName) {
            if (!$this->isOptionWithTrue($methodCall->getArgs(), $parameterName)) {
                continue;
            }
            return new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable(self::RECTOR_CONFIG_VARIABLE), $methodName);
        }
        return null;
    }
    private function refactorSetImportMethodCalls(\PhpParser\Node\Expr\Closure $closure) : void
    {
        $setConstantFetches = [];
        $lastImportKey = null;
        foreach ($closure->getStmts() as $key => $stmt) {
            if (!$stmt instanceof \PhpParser\Node\Stmt\Expression) {
                continue;
            }
            $expr = $stmt->expr;
            if (!$expr instanceof \PhpParser\Node\Expr\MethodCall) {
                continue;
            }
            if (!$this->isName($expr->name, 'import')) {
                continue;
            }
            $importArg = $expr->getArgs();
            $argValue = $importArg[0]->value;
            if (!$argValue instanceof \PhpParser\Node\Expr\ClassConstFetch) {
                continue;
            }
            $setConstantFetches[] = $argValue;
            unset($closure->stmts[$key]);
            $lastImportKey = $key;
        }
        if ($setConstantFetches === []) {
            return;
        }
        $args = $this->nodeFactory->createArgs([$setConstantFetches]);
        $setsMethodCall = new \PhpParser\Node\Expr\MethodCall(new \PhpParser\Node\Expr\Variable(self::RECTOR_CONFIG_VARIABLE), 'sets', $args);
        $closure->stmts[$lastImportKey] = new \PhpParser\Node\Stmt\Expression($setsMethodCall);
    }
}
