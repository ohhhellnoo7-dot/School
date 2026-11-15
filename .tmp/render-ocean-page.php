<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
$user = App\Models\User::where('role','administrator')->firstOrFail();

$request = Illuminate\Http\Request::create($argv[1]);
$request->setUserResolver(fn () => $user);
$route = $app['router']->getRoutes()->match($request);
$request->setRouteResolver(fn () => $route);
$app->instance('request',$request);
Illuminate\Support\Facades\Auth::setUser($user);
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$html = $route->run()->render();
$html = preg_replace_callback('/(?:href|src|data-apsara-image)="([^"]+)"/', function ($m) {
 $path = parse_url(html_entity_decode($m[1]), PHP_URL_PATH);
 if ($path && is_file(public_path(ltrim($path,'/')))) return str_replace($m[1], 'file:///'.str_replace('\\','/',public_path(ltrim($path,'/'))), $m[0]);
 return $m[0];
}, $html);
file_put_contents(__DIR__.'/ocean-page-'.$argv[2].'.html',$html);
echo "Rendered monthly attendance\n";
