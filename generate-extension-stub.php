<?php

$extension = new ReflectionExtension($argv[1]);

define('T', '    ');
define('N', PHP_EOL);

$global = [];
$namespaces = [];

$functions = $extension->getFunctions();
$classes = $extension->getClasses();
$constants = $extension->getConstants();


foreach ($constants as $cname => $cvalue) {
    putToNs(_constant($cname, $cvalue));
}

foreach ($functions as $function) {
    putToNs(_function($function));
}

foreach ($classes as $class) {
    putToNs(_class($class));
}

print doIt();

function doIt() {
    global $global, $namespaces;

    $res = '<?php' . N;
    $res .= '/**' . N . ' * Generated stub file for code completion purposes' . N . ' */';
    $res .= N . N;

    foreach ($namespaces as $ns => $php) {
        $res .= "namespace $ns {".N;
        $res .= implode(N.N, $php);
        $res .= N.'}'.N;
    }
    $res .= implode(N.N, $global);

    return $res;
}

function putToNs(array $item) {
    global $global, $namespaces;
    $ns = $item['ns'];
    $php = $item['php'];
    if($ns == null) {
        $global[] = $php;
    } else {
        if(!isset($namespaces[$ns])) {
            $namespaces[$ns] = [];
        }
        $namespaces[$ns][] = $php;
    }
}

function _constant(string $cname, $cvalue) {
    $split = explode("\\", $cname);
    $name = array_pop($split);
    $namespace = null;
    if(count($split)) {
        $namespace .= implode("\\", $split);
    }

    $res = 'const '.$name.'='.var_export($cvalue, true) .';';

    return [
        'ns' => $namespace,
        'php' => $res
    ];
}

function _class(ReflectionClass $c) {
    $res = _classModifiers($c).$c->getShortName().' ';
    if($c->getParentClass()) {
        $res .= "extends \\".$c->getParentClass()->getName().' ';
    }

    if(count($c->getInterfaces())>0) {
        $res .= 'implements ';
        $res .= implode(', ', array_map(function(ReflectionClass $i){
            return "\\".$i->getName();
        }, $c->getInterfaces()));
    }

    $res .= ' {'.N;

    foreach ($c->getTraits() as $t) {
        $res .= T."use ".$t->getName().';'.N;
    }

    foreach ($c->getConstants() as $k => $v) {
        $res .= T."const ".$k.'='.var_export($v, true).';'.N;
    }

    foreach ($c->getProperties() as $p) {
        $res.= _property($p);
    }

    /* @var $m ReflectionMethod */
    foreach ($c->getMethods() as $m) {
        if($m->getDeclaringClass() == $c) {
            $res .= _method($m);
        }
    }

    $res .= '}';

    return [
        'ns'  => $c->inNamespace() ? $c->getNamespaceName() : null ,
        'php' => $res
    ];
}

function _function(ReflectionFunction $f) {
    $res = '';
    if($f->getDocComment()) {
        $res .= $f->getDocComment();
    }
    $res .= $f->getShortName() . '(' .
        implode(', ', array_map('_argument', $f->getParameters())).')';

    if($f->getReturnType()) {
        $res .= ': '._type($f->getReturnType());
    }

    $res .= ' {}';

    return [
        'ns'  => $f->inNamespace() ? $f->getNamespaceName() : null,
        'php' => $res
    ];
}



function _classModifiers(ReflectionClass $c) {
    $res = '';
    if($c->isAbstract()) {
        $res .= 'abstract ';
    }
    if($c->isFinal()) {
        $res .= 'final ';
    }

    if($c->isTrait()) {
        $res .= 'trait ';
    } else if ($c->isInterface()) {
        $res .= 'interface ';
    } else {
        $res .= 'class ';
    }


    return $res;
}

function _property(ReflectionProperty $p) {
    $res = T;
    if($p->getDocComment()) {
        $res .= $p->getDocComment().N.T;
    }

    $res .= _propModifiers($p).'$'.$p->getName().';'.N;

    return $res;

}

function _propModifiers(ReflectionProperty $p) {
    $res = '';
    if($p->isPublic()) {
        $res .= 'public ';
    }
    if($p->isProtected()) {
        $res .= 'protected ';
    }
    if($p->isPrivate()) {
        $res .= 'private ';
    }
    if($p->isStatic()) {
        $res .= 'static ';
    }

    return $res;

}
function _method(ReflectionMethod $m) {
    /* @var $m ReflectionMethod */
    $res = T;
    if($m->getDocComment()) {
        $res .= $m->getDocComment().N.T;
    }
    $res .= _methodModifiers($m).'function '.$m->getName().' ('.
        implode(', ', array_map('_argument', $m->getParameters())).')';

    if($m->hasReturnType()) {
        $res .= ': '._type($m->getReturnType());
    }

    if(!$m->isAbstract()) {
        $res .= ' {}'.N;
    } else {
        $res .= ';'.N;
    }
    return $res;
}

function _methodModifiers(ReflectionMethod $m) {
    $res = '';
    if($m->isPublic()) {
        $res .= 'public ';
    }
    if($m->isProtected()) {
        $res .= 'protected ';
    }
    if($m->isPrivate()) {
        $res .= 'private ';
    }
    if($m->isAbstract()) {
        $res .= 'abstract ';
    }
    if($m->isStatic()) {
        $res .= 'static ';
    }
    if($m->isFinal()) {
        $res .= 'final ';
    }

    return $res;
}

function _argument(ReflectionParameter $p) {
    $res = '';
    if($type = $p->getType()) {
        $res.= _type($type).' ';
    }

    if($p->isPassedByReference()) {
        $res .= '&';
    }

    if($p->isVariadic()) {
        $res .= '...';
    }

    $res .= '$'.$p->getName();

    if($p->isOptional()) {
        if($p->isDefaultValueAvailable()) {
            $res .= '=' . var_export($p->getDefaultValue(), true);
        } else {
            $res .= "='<?>'";
        }
    }

    return $res;
}

function _type(ReflectionType $t) {
    if($t->isBuiltin()) {
        return "$t";
    } else {
        return "\\$t";
    }
}
