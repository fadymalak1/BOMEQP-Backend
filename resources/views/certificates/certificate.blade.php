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
            overflow: hidden;
        }
        
        .certificate-container {
            position: relative;
            width: {{ $width }}pt;
            height: {{ $height }}pt;
            margin: 0;
            padding: 0;
        }
        
        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $width }}pt;
            height: {{ $height }}pt;
            z-index: 1;
        }
        
        .text-element {
            position: absolute;
            z-index: 2;
            white-space: nowrap;
            line-height: 1;
        }
        
        @foreach($textElements as $index => $element)
        .text-element-{{ $index }} {
            left: {{ $element['x_pt'] }}pt;
            top: {{ $element['y_pt'] }}pt;
            font-family: '{{ $element['font_family'] }}', Arial, sans-serif;
            font-size: {{ $element['font_size'] }}pt;
            color: {{ $element['color'] }};
            text-align: {{ $element['text_align'] }};
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
