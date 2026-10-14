<?php
declare(strict_types=1);
// Ilustraciones esquemáticas para probar el catálogo, dibujadas con GD.
// No representan fotografías de piezas reales.
function demo_polygon(GdImage $im, array $points, int $color): void {
    $flat=[]; foreach($points as $point) { $flat[]=$point[0]; $flat[]=$point[1]; }
    imagefilledpolygon($im,$flat,$color);
}
function demo_canvas(string $category, int $variant): GdImage {
    $im=imagecreatetruecolor(1000,750); imageantialias($im,true);
    $color=fn($r,$g,$b)=>imagecolorallocate($im,$r,$g,$b);
    $bg=$color(239,234,222); $ink=$color(51,66,61); $paper=$color(251,247,235);
    $wood=$color(133,87,58); $dark=$color(65,55,46); $red=$color(154,64,48);
    $green=$color(79,111,98); $gold=$color(189,153,91); $blue=$color(116,147,156);
    imagefill($im,0,0,$bg);
    imagefilledellipse($im,500,607,640,45,$color(215,211,198));
    $rect=fn($x1,$y1,$x2,$y2,$c)=>imagefilledrectangle($im,$x1,$y1,$x2,$y2,$c);
    $line=fn($x1,$y1,$x2,$y2,$c)=>imageline($im,$x1,$y1,$x2,$y2,$c);
    $ellipse=fn($x,$y,$w,$h,$c)=>imagefilledellipse($im,$x,$y,$w,$h,$c);
    if ($category==='maquinas-de-escribir') {
        $rect(260,300,740,570,$variant===1?$ink:$green); $rect(295,218,705,325,$dark);
        $rect(340,140,660,285,$paper); $rect(318,280,682,306,$wood);
        for($y=175;$y<250;$y+=18) $line(372,$y,620-($y%3)*20,$y,$gold);
        $rect(290,380,710,520,$dark);
        for($r=0;$r<4;$r++) for($c=0;$c<11-$r%2;$c++) $ellipse(321+$c*36+$r%2*18,402+$r*29,23,23,$paper);
        $rect(365,535,635,552,$paper); $ellipse(277,299,45,45,$wood); $ellipse(723,299,45,45,$wood);
    } elseif ($category==='radios') {
        $rect(230,235,770,583,$variant===1?$wood:$red); $rect(255,262,745,532,$dark);
        $rect(275,280,580,512,$gold);
        for($x=280;$x<580;$x+=12) $line($x,280,$x,512,$wood);
        $rect(608,286,721,342,$paper);
        for($x=615;$x<720;$x+=10) $line($x,295,$x,325,$ink);
        $ellipse(641,400,57,57,$gold); $ellipse(690,472,42,42,$paper);
        $rect(285,582,325,602,$dark); $rect(675,582,715,602,$dark);
        if($variant===2) { $line(360,235,360,198,$dark); $line(640,235,640,198,$dark); $rect(360,190,640,205,$dark); }
    } elseif ($category==='televisores') {
        $line(489,220,390,140,$ink); $line(495,220,590,125,$ink);
        $rect(220,245,780,570,$variant===1?$wood:$paper); $rect(250,272,659,540,$dark);
        $ellipse(456,405,375,235,$blue); $rect(302,333,609,478,$blue);
        for($y=310;$y<510;$y+=8) $line(300,$y,610,$y,$color(104,137,144));
        $ellipse(708,322,45,45,$ink); $ellipse(708,395,36,36,$ink);
        for($y=450;$y<520;$y+=10) $line(685,$y,739,$y,$ink);
        $line(310,570,288,618,$dark); $line(690,570,712,618,$dark);
    } elseif (in_array($category,['fotografias','dibujos','pinturas','grabados'],true)) {
        $rect(260,130,740,610,$category==='fotografias'?$gold:$wood);
        $rect(279,149,721,591,$paper); $rect(310,180,690,560,$category==='pinturas'?$blue:$color(222,210,182));
        if($category==='fotografias' && $variant===1) {
            $ellipse(500,435,200,210,$dark); $ellipse(500,310,105,137,$wood); $ellipse(500,275,122,75,$dark);
            demo_polygon($im,[[475,382],[500,415],[525,382],[540,435],[460,435]],$paper);
        } elseif ($category==='fotografias' && $variant===2) {
            $rect(310,180,690,560,$color(204,201,188));
            $rect(310,255,400,505,$wood); $rect(405,220,500,505,$dark); $rect(570,270,690,505,$wood);
            for($x=320;$x<690;$x+=32) for($y=290;$y<475;$y+=38) $rect($x,$y,$x+12,$y+18,$paper);
            demo_polygon($im,[[470,560],[520,405],[550,405],[610,560]],$paper);
            $line(541,205,541,470,$ink); $ellipse(541,205,24,24,$paper);
        } elseif ($category==='dibujos') {
            imagesetthickness($im,$category==='dibujos'?3:1);
            $ellipse(499,334,94,125,$paper); imageellipse($im,499,334,100,133,$ink);
            imagearc($im,498,460,235,160,180,360,$ink); $line(451,323,469,315,$ink); $line(525,314,542,322,$ink);
            $line(499,330,489,362,$ink); $line(489,362,507,362,$ink); $line(480,383,520,383,$ink);
            for($i=0;$i<20;$i++) $line(400+$i*4,450+$i%3*7,437+$i*4,520,$wood);
        } else {
            $ellipse(600,255,67,67,$gold);
            demo_polygon($im,[[310,430],[398,310],[515,455],[585,355],[690,432],[690,560],[310,560]],$green);
            demo_polygon($im,[[310,510],[425,420],[525,493],[690,465],[690,560],[310,560]],$wood);
            if($category==='grabados') {
                imagesetthickness($im,1);
                for($x=314;$x<690;$x+=9) $line($x,185,$x,555,$ink);
                for($y=430;$y<555;$y+=13) $line(314,$y,688,$y,$ink);
            }
        }
    } elseif ($category==='botellas') {
        $glass=$variant===1?$green:$wood;
        $rect(447,150,553,260,$glass); $rect(431,144,569,170,$dark);
        $ellipse(500,330,260,180,$glass); $rect(370,330,630,571,$glass); $ellipse(500,570,260,54,$glass);
        $rect(399,358,601,505,$paper); $rect(415,372,585,385,$gold);
        imagestring($im,5,446,414,$variant===1?'BOTICA':'ENVASE',$ink);
        $line(405,284,405,340,$paper); $rect(389,342,399,546,$color(148,164,135));
    } elseif ($category==='naipes') {
        for($c=0;$c<3;$c++) {
            $x=255+$c*150; $y=210+$c*24; $rect($x+5,$y+8,$x+231,$y+328,$gold); $rect($x,$y,$x+225,$y+320,$paper);
            imagerectangle($im,$x+10,$y+10,$x+215,$y+310,$red);
            imagestring($im,5,$x+20,$y+20,(string)($c+1),$red);
            $ellipse($x+112,$y+155,76,76,$variant===1?$gold:$red);
            if($variant===2) { demo_polygon($im,[[$x+74,$y+155],[$x+150,$y+155],[$x+112,$y+224]],$red); }
        }
    } elseif ($category==='figuritas') {
        for($c=0;$c<3;$c++) {
            $x=210+$c*195; $y=210+($c%2)*40; $rect($x,$y,$x+175,$y+280,$paper); $rect($x+12,$y+12,$x+163,$y+220,$variant===1?$green:$blue);
            if($variant===1) { $ellipse($x+88,$y+67,45,50,$gold); $rect($x+57,$y+94,$x+118,$y+155,$red); $line($x+68,$y+155,$x+55,$y+203,$ink); $line($x+107,$y+155,$x+121,$y+203,$ink); }
            else { $ellipse($x+88,$y+115,100,100,$gold); $ellipse($x+112,$y+92,100,100,$blue); }
            imagestring($im,5,$x+47,$y+244,'No. '.($c+1+$variant*10),$ink);
        }
    }
    if($variant===2 && in_array($category,['pinturas','grabados'],true)) imageflip($im,IMG_FLIP_HORIZONTAL);
    $rect(55,55,100,59,$red); imagestring($im,4,55,75,'PRODIGO / DEMO',$ink);
    imagestring($im,3,55,690,'ILUSTRACION DE EJEMPLO - NO ES UNA PIEZA REAL',$ink);
    return $im;
}
function demo_image(string $category, int $variant, bool $detail=false): array {
    ensure_storage(); $source=demo_canvas($category,$variant); $token=bin2hex(random_bytes(16)); $batch=['token'=>$token,'variants'=>[]];
    try {
        if (!mkdir(STORAGE.'/images/'.$token,0770,true)) throw new RuntimeException('No se pudo crear la carpeta de ejemplos.');
        foreach (['thumbnail'=>420,'web'=>1000,'zoom'=>1000] as $name=>$max) {
            $w=$max; $hh=(int)round($max*.75); $canvas=imagecreatetruecolor($w,$hh);
            $sx=$detail?310:0; $sy=$detail?190:0; $sw=$detail?400:1000; $sh=$detail?300:750;
            imagecopyresampled($canvas,$source,0,0,$sx,$sy,$w,$hh,$sw,$sh);
            if($detail) imagestring($canvas,3,12,12,'DEMO / DETALLE',imagecolorallocate($canvas,50,60,50));
            $key=$token.'/'.$name.'.jpg';
            $batch['variants'][$name]=['key'=>$key,'width'=>$w,'height'=>$hh,'bytes'=>0];
            try { if(!imagejpeg($canvas,image_key($key),87)) throw new RuntimeException('Falló la imagen de ejemplo.'); }
            finally { imagedestroy($canvas); }
            $batch['variants'][$name]['bytes']=filesize(image_key($key));
        }
        return $batch;
    } catch(Throwable $e) { remove_generated([$batch]); throw $e; }
    finally { imagedestroy($source); }
}
