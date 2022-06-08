<?php

$restApiReference = './docs/api/rest_api_reference/rest_api_reference.html';
$dxpRoot = $argv[1];
$testedRoutes = ReferenceTester::TEST_ALL_ROUTES;
//$testedRoutes = ReferenceTester::TEST_REFERENCE_ROUTES;

$referenceTester = new ReferenceTester($restApiReference, $dxpRoot);
$referenceTester->run($testedRoutes);

class ReferenceTester
{
    const TEST_REFERENCE_ROUTES = 1;
    const TEST_CONFIG_ROUTES = 2;
    const TEST_ALL_ROUTES = 3;

    private $restApiReference;
    private $dxpRoot;
    private $routingFiles;

    private $refRoutes;
    private $confRoutes;

    public function __construct($restApiReference, $dxpRoot, $routingFiles = null)
    {
        if (!is_file($restApiReference)) {
            user_error("$restApiReference doesn't exist or is not a file", E_USER_ERROR);
            exit(1);
        }
        if (!is_dir($dxpRoot)) {
            user_error("$dxpRoot doesn't exist or is not a directory", E_USER_ERROR);
            exit(2);
        }

        $this->restApiReference = $restApiReference;
        $this->dxpRoot = $dxpRoot;
        $this->routingFiles = $routingFiles ?? [
                'vendor/ibexa/rest/src/bundle/Resources/config/routing.yml',
                'vendor/ibexa/commerce-rest/src/bundle/Resources/config/routing.yaml',
                // `find $dxpRoot/vendor/ibexa -name "routing_rest.y*ml"`
                //'vendor/ibexa/admin-ui/src/bundle/Resources/config/routing_rest.yaml',
                'vendor/ibexa/calendar/src/bundle/Resources/config/routing_rest.yaml',
                'vendor/ibexa/connector-dam/src/bundle/Resources/config/routing_rest.yaml',
                'vendor/ibexa/personalization/src/bundle/Resources/config/routing_rest.yaml',
                'vendor/ibexa/product-catalog/src/bundle/Resources/config/routing_rest.yaml',
                'vendor/ibexa/scheduler/src/bundle/Resources/config/routing_rest.yaml',
                'vendor/ibexa/taxonomy/src/bundle/Resources/config/routing_rest.yaml',
            ];
        $this->parse();
    }

    private function parse(): void
    {
        $parsedRoutingFiles = [];
        foreach ($this->routingFiles as $routingFile) {
            $routingFilePath = "{$this->dxpRoot}/$routingFile";
            if (!is_file($routingFilePath)) {
                user_error("$routingFilePath doesn't exist or is not a file", E_USER_WARNING);
                continue;
            }
            $parsedRoutingFiles[$routingFile] = yaml_parse_file($routingFilePath);
        }

        $restApiRefDoc = new DOMDocument();
        $restApiRefDoc->loadHTMLFile($this->restApiReference, LIBXML_NOERROR);
        $restApiRefXpath = new DOMXpath($restApiRefDoc);

        $refRoutes = [];
        /** @var DOMElement $urlElement */
        foreach ($restApiRefXpath->query('//*[@data-field="url"]') as $urlElement) {
            if (!array_key_exists($urlElement->nodeValue, $refRoutes)) {
                $refRoutes[$urlElement->nodeValue] = [
                    'methods' => [],
                ];
            }
            $refRoutes[$urlElement->nodeValue]['methods'][$urlElement->previousSibling->previousSibling->nodeValue] = true;
        }

        $confRoutes = [];
        foreach ($parsedRoutingFiles as $routingFile => $parsedRoutingFile) {
            foreach ($parsedRoutingFile as $routeId => $routeDef) {
                $line = (int)explode(':', `grep -n '^$routeId:$' {$this->dxpRoot}/$routingFile`)[0];
                if (!array_key_exists('methods', $routeDef)) {
                    user_error("$routeId ($routingFile@$line) matches every methods by default; skipped", E_USER_WARNING);
                    continue;
                }
                if (!array_key_exists($routeDef['path'], $confRoutes)) {
                    $confRoutes[$routeDef['path']] = [
                        'methods' => [],
                    ];
                }
                foreach ($routeDef['methods'] as $method) {
                    $confRoutes[$routeDef['path']]['methods'][$method] = [
                        'id' => $routeId,
                        'file' => $routingFile,
                        'line' => $line,
                    ];
                }
            }
        }

        $this->refRoutes = $refRoutes;
        $this->confRoutes = $confRoutes;
    }

    public function run(int $testedRoutes = self::TEST_ALL_ROUTES)
    {
        $refRoutes = $this->refRoutes;
        $confRoutes = $this->confRoutes;

        foreach (array_intersect(array_keys($refRoutes), array_keys($confRoutes)) as $commonRoute) {
            $missingMethods = $this->compareMethods($commonRoute, $commonRoute, $testedRoutes);
            if ($missingMethods && false !== strpos($commonRoute, '{')) {
                $similarRefRoutes = $this->getSimilarRoutes($commonRoute, $refRoutes);
                $similarConfRoutes = $this->getSimilarRoutes($commonRoute, $confRoutes);
                foreach (['highly', 'poorly'] as $similarityLevel) {
                    foreach ($similarRefRoutes[$similarityLevel] as $refRoute) {
                        if ($refRoute === $commonRoute) {
                            continue;
                        }
                        $stillMissingMethod = $this->compareMethods($refRoute, $commonRoute, $testedRoutes, $missingMethods);
                        $foundMethods = array_diff($missingMethods, $stillMissingMethod);
                        if (!empty($foundMethods)) {
                            foreach ($foundMethods as $foundMethod) {
                                if ('highly' === $similarityLevel) {
                                    echo "\t$refRoute has $foundMethod and is highly similar to $commonRoute\n";
                                } else {
                                    echo "\t$refRoute has $foundMethod and is a bit similar to $commonRoute\n";
                                }
                            }
                        }
                    }
                    foreach ($similarConfRoutes[$similarityLevel] as $confRoute) {
                        if ($confRoute === $commonRoute) {
                            continue;
                        }
                        $stillMissingMethod = $this->compareMethods($commonRoute, $confRoute, $testedRoutes, $missingMethods);
                        $foundMethods = array_diff($missingMethods, $stillMissingMethod);
                        if (!empty($foundMethods)) {
                            foreach ($foundMethods as $foundMethod) {
                                if ('highly' === $similarityLevel) {
                                    echo "\t{$this->getConfRoutePrompt($confRoute)} has $foundMethod and is highly similar to $commonRoute\n";
                                } else {
                                    echo "\t{$this->getConfRoutePrompt($confRoute)} has $foundMethod and is a bit similar to $commonRoute\n";
                                }
                            }
                        }
                    }
                }
            }
        }

        if (self::TEST_REFERENCE_ROUTES & $testedRoutes) {
            foreach (array_diff(array_keys($refRoutes), array_keys($confRoutes)) as $refRouteWithoutConf) {
                if (false !== strpos($refRouteWithoutConf, '{')) {
                    $similarConfRoutes = $this->getSimilarRoutes($refRouteWithoutConf, $confRoutes);
                    if (!empty($similarConfRoutes['highly'])) {
                        echo "$refRouteWithoutConf not found in config files but\n";
                        foreach ($similarConfRoutes['highly'] as $confRoute) {
                            echo "\t$refRouteWithoutConf is highly similar to $confRoute\n";
                            $this->compareMethods($refRouteWithoutConf, $confRoute, $testedRoutes);
                        }
                        continue;
                    }
                    if (!empty($similarConfRoutes['poorly'])) {
                        echo "$refRouteWithoutConf not found in config files but\n";
                        foreach ($similarConfRoutes['poorly'] as $confRoute) {
                            echo "\t$refRouteWithoutConf is a bit similar to $confRoute\n";
                            $this->compareMethods($refRouteWithoutConf, $confRoute, $testedRoutes);
                        }
                        continue;
                    }
                }
                echo "$refRouteWithoutConf not found in config files.\n";
            }
        }

        if (self::TEST_CONFIG_ROUTES & $testedRoutes) {
            foreach (array_diff(array_keys($confRoutes), array_keys($refRoutes)) as $confRouteWithoutRef) {
                if (false !== strpos($confRouteWithoutRef, '{')) {
                    $similarRefRoutes = $this->getSimilarRoutes($confRouteWithoutRef, $refRoutes);
                    if (!empty($similarRefRoutes['highly'])) {
                        echo "{$this->getConfRoutePrompt($confRouteWithoutRef)} not found in reference but\n";
                        foreach ($similarRefRoutes['highly'] as $refRoute) {
                            echo "\t$confRouteWithoutRef is highly similar to $refRoute\n";
                            $this->compareMethods($refRoute, $confRouteWithoutRef, $testedRoutes);
                        }
                        continue;
                    }
                    if (!empty($similarRefRoutes['poorly'])) {
                        echo "{$this->getConfRoutePrompt($confRouteWithoutRef)} not found in reference but\n";
                        foreach ($similarRefRoutes['poorly'] as $refRoute) {
                            echo "\t$confRouteWithoutRef is a bit similar to $refRoute\n";
                            $this->compareMethods($refRoute, $confRouteWithoutRef, $testedRoutes);
                        }
                        continue;
                    }
                }
                echo "{$this->getConfRoutePrompt($confRouteWithoutRef)} not found in reference.\n";
            }
        }
    }

    private function compareMethods(string $refRoute, string $confRoute, int $testedRoutes = self::TEST_ALL_ROUTES, ?array $testedMethods = null): array
    {
        $refRoutes = $this->refRoutes;
        $confRoutes = $this->confRoutes;
        $missingMethods = [];

        if (self::TEST_REFERENCE_ROUTES & $testedRoutes) {
            foreach (array_diff(array_keys($refRoutes[$refRoute]['methods']), array_keys($confRoutes[$confRoute]['methods'])) as $refMethodWithoutConf) {
                if (null === $testedMethods || in_array($refMethodWithoutConf, $testedMethods)) {
                    echo "$refRoute: $refMethodWithoutConf method not found in conf files" . ($refRoute === $confRoute ? '' : " (while comparing to $confRoute)") . ".\n";
                    $missingMethods[] = $refMethodWithoutConf;
                }
            }
        }

        if (self::TEST_CONFIG_ROUTES & $testedRoutes) {
            foreach (array_diff(array_keys($confRoutes[$confRoute]['methods']), array_keys($refRoutes[$refRoute]['methods'])) as $confMethodWithoutRef) {
                if (null === $testedMethods || in_array($confMethodWithoutRef, $testedMethods)) {
                    echo "{$this->getConfRoutePrompt($confRoute, $confMethodWithoutRef)}: $confMethodWithoutRef not found in reference" . ($refRoute === $confRoute ? '' : " (while comparing to $refRoute)") . ".\n";;
                    $missingMethods[] = $confMethodWithoutRef;
                }
            }
        }

        return $missingMethods;
    }

    private function getSimilarRoutes(string $path, array $routeCollection): array
    {
        $routePattern = $this->getRoutePattern($path);
        $highlySimilarRoutes = [];
        $poorlySimilarRoutes = [];
        foreach (array_keys($routeCollection) as $route) {
            if (preg_match($routePattern, $route)) {
                if ($this->getSimplifiedRoute($route) === $this->getSimplifiedRoute($path)) {
                    $highlySimilarRoutes[] = $route;
                } else {
                    $poorlySimilarRoutes[] = $route;
                }
            }
        }
        return [
            'highly' => $highlySimilarRoutes,
            'poorly' => $poorlySimilarRoutes,
        ];
    }

    private function getSimplifiedRoute(string $path): string
    {
        return str_replace(['identifier', 'number', '_', '-'], ['id', 'no', ''], strtolower($path));
    }

    private function getRoutePattern(string $path): string
    {
        return '@^' . preg_replace('@\{[^}]+\}@', '\{[^}]+\}', $path) . '$@';
    }

    private function getConfRoutePrompt(string $path, $method = null): string
    {
        if (array_key_exists($path, $this->confRoutes)) {
            if ($method && array_key_exists($method, $this->confRoutes[$path]['methods'])) {
                return "$path ({$this->confRoutes[$path]['methods'][$method]['file']}@{$this->confRoutes[$path]['methods'][$method]['line']})";
            } else {
                $files = [];
                $lines = [];
                $pairs = [];
                foreach ($this->confRoutes[$path]['methods'] as $methodDetail) {
                    $files[] = $methodDetail['file'];
                    $lines[] = $methodDetail['line'];
                    $pairs[] = "{$methodDetail['file']}@{$methodDetail['line']}";
                }
                $filteredFiles = array_unique($files);
                if (1 < count($filteredFiles)) {
                    $pairs = implode(',', array_unique($pairs));
                    return "$path ($pairs)";
                } else {
                    $file = $filteredFiles[0];
                    $lines = implode(',', array_unique($lines));
                    return "$path ($file@$lines)";
                }
            }
        }

        return $path;
    }
}
