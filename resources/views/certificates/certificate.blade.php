<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <title>Certificate</title>
    <style>
        @page {
            margin: 0;
            size: {{ $width }}pt {{ $height }}pt;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            width: {{ $width }}pt;
            height: {{ $height }}pt;
            position: relative;
            font-family: Arial, sans-serif;
        }
        
        .certificate-container {
            position: relative;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            object-fit: contain;
        }
        
        .text-element {
            position: absolute;
            z-index: 2;
            white-space: nowrap;
            line-height: 1.2;
        }
        
        @foreach($textElements as $index => $element)
        .text-element-{{ $index }} {
            left: {{ $element['x_percent'] * 100 }}%;
            top: {{ $element['y_percent'] * 100 }}%;
            font-family: {{ $element['font_family'] }}, Arial, sans-serif;
            font-size: {{ $element['font_size'] }}pt;
            color: {{ $element['color'] }};
            @if($element['text_align'] === 'center')
                transform: translateX(-50%);
                text-align: center;
            @elseif($element['text_align'] === 'right')
                transform: translateX(-100%);
                text-align: right;
            @else
                text-align: left;
            @endif
        }
        @endforeach
    </style>
</head>
<body>
    <div class="certificate-container">
        <img src="{{ $backgroundImage }}" alt="Certificate Background" class="background-image" />
        
        @foreach($textElements as $index => $element)
        <div class="text-element text-element-{{ $index }}">
            {{ $element['text'] }}
        </div>
        @endforeach
    </div>
</body>
</html>

