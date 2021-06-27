<?php

declare (strict_types=1);
namespace Rector\Core\Reflection;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;
use Rector\Core\ValueObject\MethodName;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\NodeTypeResolver;
use ReflectionMethod;
final class ReflectionResolver
{
    /**
     * @var \PHPStan\Reflection\ReflectionProvider
     */
    private $reflectionProvider;
    /**
     * @var \Rector\NodeTypeResolver\NodeTypeResolver
     */
    private $nodeTypeResolver;
    /**
     * @var \Rector\NodeNameResolver\NodeNameResolver
     */
    private $nodeNameResolver;
    public function __construct(\PHPStan\Reflection\ReflectionProvider $reflectionProvider, \Rector\NodeTypeResolver\NodeTypeResolver $nodeTypeResolver, \Rector\NodeNameResolver\NodeNameResolver $nodeNameResolver)
    {
        $this->reflectionProvider = $reflectionProvider;
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->nodeNameResolver = $nodeNameResolver;
    }
    /**
     * @param class-string $className
     */
    public function resolveMethodReflection(string $className, string $methodName) : ?\PHPStan\Reflection\MethodReflection
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }
        $classReflection = $this->reflectionProvider->getClass($className);
        if ($classReflection->hasMethod($methodName)) {
            return $classReflection->getNativeMethod($methodName);
        }
        return null;
    }
    /**
     * @param class-string $className
     */
    public function resolveNativeClassMethodReflection(string $className, string $methodName) : ?\ReflectionMethod
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }
        $classReflection = $this->reflectionProvider->getClass($className);
        $reflectionClass = $classReflection->getNativeReflection();
        return $reflectionClass->hasMethod($methodName) ? $reflectionClass->getMethod($methodName) : null;
    }
    public function resolveMethodReflectionFromStaticCall(\PhpParser\Node\Expr\StaticCall $staticCall) : ?\PHPStan\Reflection\MethodReflection
    {
        $objectType = $this->nodeTypeResolver->resolve($staticCall->class);
        /** @var array<class-string> $classes */
        $classes = \PHPStan\Type\TypeUtils::getDirectClassNames($objectType);
        $methodName = $this->nodeNameResolver->getName($staticCall->name);
        if ($methodName === null) {
            return null;
        }
        foreach ($classes as $class) {
            $methodReflection = $this->resolveMethodReflection($class, $methodName);
            if ($methodReflection instanceof \PHPStan\Reflection\MethodReflection) {
                return $methodReflection;
            }
        }
        return null;
    }
    public function resolveMethodReflectionFromMethodCall(\PhpParser\Node\Expr\MethodCall $methodCall) : ?\PHPStan\Reflection\MethodReflection
    {
        $callerType = $this->nodeTypeResolver->resolve($methodCall->var);
        if (!$callerType instanceof \PHPStan\Type\TypeWithClassName) {
            return null;
        }
        $methodName = $this->nodeNameResolver->getName($methodCall->name);
        if ($methodName === null) {
            return null;
        }
        return $this->resolveMethodReflection($callerType->getClassName(), $methodName);
    }
    /**
     * @param \PhpParser\Node\Expr\MethodCall|\PhpParser\Node\Expr\StaticCall|\PhpParser\Node\Expr\FuncCall $call
     * @return \PHPStan\Reflection\MethodReflection|\PHPStan\Reflection\FunctionReflection|null
     */
    public function resolveFunctionLikeReflectionFromCall($call)
    {
        if ($call instanceof \PhpParser\Node\Expr\MethodCall) {
            return $this->resolveMethodReflectionFromMethodCall($call);
        }
        if ($call instanceof \PhpParser\Node\Expr\StaticCall) {
            return $this->resolveMethodReflectionFromStaticCall($call);
        }
        return $this->resolveFunctionReflectionFromFuncCall($call);
    }
    public function resolveMethodReflectionFromClassMethod(\PhpParser\Node\Stmt\ClassMethod $classMethod) : ?\PHPStan\Reflection\MethodReflection
    {
        $class = $classMethod->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::CLASS_NAME);
        if ($class === null) {
            return null;
        }
        $methodName = $this->nodeNameResolver->getName($classMethod);
        return $this->resolveMethodReflection($class, $methodName);
    }
    public function resolveMethodReflectionFromNew(\PhpParser\Node\Expr\New_ $new) : ?\PHPStan\Reflection\MethodReflection
    {
        $newClassType = $this->nodeTypeResolver->resolve($new->class);
        if (!$newClassType instanceof \PHPStan\Type\TypeWithClassName) {
            return null;
        }
        return $this->resolveMethodReflection($newClassType->getClassName(), \Rector\Core\ValueObject\MethodName::CONSTRUCT);
    }
    private function resolveFunctionReflectionFromFuncCall(\PhpParser\Node\Expr\FuncCall $funcCall) : ?\PHPStan\Reflection\FunctionReflection
    {
        $functionName = $this->nodeNameResolver->getName($funcCall);
        if ($functionName === null) {
            return null;
        }
        $functionNameFullyQualified = new \PhpParser\Node\Name\FullyQualified($functionName);
        if (!$this->reflectionProvider->hasFunction($functionNameFullyQualified, null)) {
            return null;
        }
        return $this->reflectionProvider->getFunction($functionNameFullyQualified, null);
    }
}
