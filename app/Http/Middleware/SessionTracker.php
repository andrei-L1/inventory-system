<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;

class SessionTracker
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track if we have a session ID and it's a database session
        if ($request->hasSession() && config('session.driver') === 'database') {
            $sessionId = $request->session()->getId();

            // Production Optimization: Only resolve and update if metadata is missing
            $sessionData = DB::table(config('session.table', 'sessions'))
                ->where('id', $sessionId)
                ->first();

            if ($sessionData && empty($sessionData->device_type)) {
                $userAgent = $request->userAgent();
                $ip = $request->ip();

                $agent = new Agent;
                $agent->setUserAgent($userAgent);

                $deviceType = $this->getDeviceType($agent);
                $browser = $agent->browser();
                $browserVersion = $agent->version($browser);
                $platform = $agent->platform();
                $platformVersion = $agent->version($platform);

                $deviceName = $agent->device();
                if ($deviceType === 'desktop' && (empty($deviceName) || in_array($deviceName, ['WebKit', 'Gecko']))) {
                    $deviceName = 'Personal Computer';
                }

                // Geolocation with Local Fallback (Strictly for development)
                if (($ip === '127.0.0.1' || $ip === '::1') && app()->environment('local')) {
                    try {
                        $context = stream_context_create(['http' => ['timeout' => 2]]);
                        $externalIp = @file_get_contents('https://api.ipify.org', false, $context);
                        $ip = $externalIp ?: '8.8.8.8';
                    } catch (\Exception $e) {
                        $ip = '8.8.8.8';
                    }
                }

                $location = Location::get($ip);
                $country = $location ? $location->countryName : 'Unknown';
                $city = $location ? $location->cityName : 'Unknown';

                DB::table(config('session.table', 'sessions'))
                    ->where('id', $sessionId)
                    ->update([
                        'device_type' => $deviceType,
                        'device_name' => $deviceName,
                        'browser' => $browser.($browserVersion ? " $browserVersion" : ''),
                        'platform' => $platform.($platformVersion ? " $platformVersion" : ''),
                        'country' => $country,
                        'city' => $city,
                        'ip_address' => $request->ip(), // Record the actual IP, not the resolved one
                        'user_id' => auth()->id(),
                    ]);
            }
        }

        return $response;
    }

    private function getDeviceType(Agent $agent): string
    {
        if ($agent->isTablet()) {
            return 'tablet';
        }
        if ($agent->isMobile()) {
            return 'mobile';
        }
        if ($agent->isRobot()) {
            return 'robot';
        }

        return 'desktop';
    }
}
