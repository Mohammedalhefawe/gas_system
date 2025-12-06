<?php

namespace App\Services;

class GeoService
{
    /**
     * Check if given latitude & longitude are inside a polygon
     */
    public function pointInPolygon($lat, $lng, $polygon)
    {
        $inside = false;
        $numPoints = count($polygon);
        $j = $numPoints - 1;

        for ($i = 0; $i < $numPoints; $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lng'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lng'];

            $intersect = (($yi > $lng) != ($yj > $lng)) &&
                ($lat < ($xj - $xi) * ($lng - $yi) / (($yj - $yi) ?: 0.0000001) + $xi);
            if ($intersect) $inside = !$inside;

            $j = $i;
        }

        return $inside;
    }
}
 