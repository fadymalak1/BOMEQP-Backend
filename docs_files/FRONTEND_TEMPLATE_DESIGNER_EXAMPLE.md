# Frontend Template Designer - React/Vue Examples

This document provides example code for implementing the certificate template designer using Fabric.js (recommended) or Konva.js.

## Tech Stack Recommendation

- **Canvas Library**: Fabric.js (easier to use) or Konva.js (better performance)
- **Framework**: React or Vue
- **File Upload**: Standard file input or drag-and-drop library

## React + Fabric.js Example

### Installation

```bash
npm install fabric react-fabricjs
# or
yarn add fabric react-fabricjs
```

### Component Code

```jsx
import React, { useRef, useEffect, useState } from 'react';
import { fabric } from 'fabric';
import axios from 'axios';

const CertificateTemplateDesigner = ({ templateId, onSave }) => {
  const canvasRef = useRef(null);
  const canvas = useRef(null);
  const [backgroundImage, setBackgroundImage] = useState(null);
  const [placeholders, setPlaceholders] = useState([]);
  const [selectedPlaceholder, setSelectedPlaceholder] = useState(null);
  const [imageFile, setImageFile] = useState(null);

  // Initialize canvas
  useEffect(() => {
    if (!canvasRef.current) return;

    canvas.current = new fabric.Canvas(canvasRef.current, {
      width: 1200,
      height: 848, // A4 ratio
      backgroundColor: '#ffffff',
    });

    // Load existing template if editing
    if (templateId) {
      loadTemplate();
    }

    return () => {
      if (canvas.current) {
        canvas.current.dispose();
      }
    };
  }, [templateId]);

  // Load background image
  const handleImageUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    setImageFile(file);

    const reader = new FileReader();
    reader.onload = (e) => {
      const imgUrl = e.target.result;
      
      fabric.Image.fromURL(imgUrl, (img) => {
        // Scale image to fit canvas while maintaining aspect ratio
        const canvasWidth = canvas.current.width;
        const canvasHeight = canvas.current.height;
        const imgWidth = img.width;
        const imgHeight = img.height;
        
        const scale = Math.min(canvasWidth / imgWidth, canvasHeight / imgHeight);
        img.scale(scale);
        
        // Center image
        img.set({
          left: (canvasWidth - imgWidth * scale) / 2,
          top: (canvasHeight - imgHeight * scale) / 2,
          selectable: false,
          evented: false,
        });
        
        canvas.current.setBackgroundImage(img, canvas.current.renderAll.bind(canvas.current));
        setBackgroundImage(img);
      });
    };
    reader.readAsDataURL(file);
  };

  // Add placeholder
  const addPlaceholder = (variableName) => {
    const text = new fabric.Text(`{{${variableName}}}`, {
      left: canvas.current.width / 2,
      top: canvas.current.height / 2,
      fontSize: 24,
      fill: '#000000',
      fontFamily: 'Arial',
      textAlign: 'center',
      originX: 'center',
      originY: 'center',
    });

    // Store variable name as metadata
    text.variable = variableName;
    
    canvas.current.add(text);
    canvas.current.setActiveObject(text);
    
    // Update placeholders list
    updatePlaceholdersList();
  };

  // Update placeholder properties
  const updatePlaceholderProperties = (property, value) => {
    const activeObject = canvas.current.getActiveObject();
    if (!activeObject || !activeObject.variable) return;

    activeObject.set(property, value);
    canvas.current.renderAll();
    updatePlaceholdersList();
  };

  // Update placeholders list from canvas objects
  const updatePlaceholdersList = () => {
    const objects = canvas.current.getObjects();
    const placeholderObjects = objects.filter(obj => obj.variable);
    
    setPlaceholders(placeholderObjects.map(obj => ({
      variable: obj.variable,
      x: obj.left / canvas.current.width,
      y: obj.top / canvas.current.height,
      fontFamily: obj.fontFamily,
      fontSize: obj.fontSize,
      color: obj.fill,
      textAlign: obj.textAlign || 'left',
    })));
  };

  // Handle object selection
  useEffect(() => {
    if (!canvas.current) return;

    canvas.current.on('selection:created', (e) => {
      const obj = e.selected[0];
      if (obj && obj.variable) {
        setSelectedPlaceholder(obj.variable);
      }
    });

    canvas.current.on('selection:updated', (e) => {
      const obj = e.selected[0];
      if (obj && obj.variable) {
        setSelectedPlaceholder(obj.variable);
      }
    });

    canvas.current.on('selection:cleared', () => {
      setSelectedPlaceholder(null);
    });

    canvas.current.on('object:modified', () => {
      updatePlaceholdersList();
    });
  }, []);

  // Save template configuration
  const saveTemplate = async () => {
    // First upload background image if new
    let backgroundImageUrl = backgroundImage?.getSrc();
    
    if (imageFile) {
      const formData = new FormData();
      formData.append('background_image', imageFile);
      
      const uploadResponse = await axios.post(
        `/api/acc/certificate-templates/${templateId}/upload-background`,
        formData,
        {
          headers: { 'Content-Type': 'multipart/form-data' },
        }
      );
      
      backgroundImageUrl = uploadResponse.data.background_image_url;
    }

    // Save configuration
    const config = placeholders.map(p => ({
      variable: `{{${p.variable}}}`,
      x: p.x,
      y: p.y,
      font_family: p.fontFamily,
      font_size: p.fontSize,
      color: p.color,
      text_align: p.textAlign,
    }));

    await axios.put(
      `/api/acc/certificate-templates/${templateId}/config`,
      { config_json: config }
    );

    if (onSave) {
      onSave();
    }
  };

  // Load existing template
  const loadTemplate = async () => {
    try {
      const response = await axios.get(`/api/acc/certificate-templates/${templateId}`);
      const template = response.data.template;

      // Load background image
      if (template.background_image_url) {
        fabric.Image.fromURL(template.background_image_url, (img) => {
          const canvasWidth = canvas.current.width;
          const canvasHeight = canvas.current.height;
          const scale = Math.min(canvasWidth / img.width, canvasHeight / img.height);
          img.scale(scale);
          img.set({
            left: (canvasWidth - img.width * scale) / 2,
            top: (canvasHeight - img.height * scale) / 2,
            selectable: false,
            evented: false,
          });
          canvas.current.setBackgroundImage(img, canvas.current.renderAll.bind(canvas.current));
          setBackgroundImage(img);
        });
      }

      // Load placeholders
      if (template.config_json && Array.isArray(template.config_json)) {
        template.config_json.forEach(config => {
          const variableName = config.variable.replace(/[{}]/g, '');
          const text = new fabric.Text(config.variable, {
            left: config.x * canvas.current.width,
            top: config.y * canvas.current.height,
            fontSize: config.font_size || 24,
            fill: config.color || '#000000',
            fontFamily: config.font_family || 'Arial',
            textAlign: config.text_align || 'left',
            originX: config.text_align === 'center' ? 'center' : 'left',
            originY: 'center',
          });
          text.variable = variableName;
          canvas.current.add(text);
        });
        updatePlaceholdersList();
      }
    } catch (error) {
      console.error('Error loading template:', error);
    }
  };

  const activeObject = canvas.current?.getActiveObject();
  const selectedConfig = placeholders.find(p => p.variable === selectedPlaceholder);

  return (
    <div className="template-designer">
      <div className="toolbar">
        <input
          type="file"
          accept="image/jpeg,image/png"
          onChange={handleImageUpload}
        />
        <button onClick={() => addPlaceholder('student_name')}>
          Add Student Name
        </button>
        <button onClick={() => addPlaceholder('course_name')}>
          Add Course Name
        </button>
        <button onClick={() => addPlaceholder('date')}>
          Add Date
        </button>
        <button onClick={() => addPlaceholder('cert_id')}>
          Add Certificate ID
        </button>
      </div>

      <div className="canvas-container">
        <canvas ref={canvasRef} />
      </div>

      {activeObject && selectedConfig && (
        <div className="properties-panel">
          <h3>Properties: {{selectedPlaceholder}}</h3>
          
          <label>
            Font Family:
            <select
              value={selectedConfig.fontFamily}
              onChange={(e) => updatePlaceholderProperties('fontFamily', e.target.value)}
            >
              <option value="Arial">Arial</option>
              <option value="Times New Roman">Times New Roman</option>
              <option value="Courier">Courier</option>
            </select>
          </label>

          <label>
            Font Size:
            <input
              type="number"
              value={selectedConfig.fontSize}
              onChange={(e) => updatePlaceholderProperties('fontSize', parseInt(e.target.value))}
              min="8"
              max="200"
            />
          </label>

          <label>
            Color:
            <input
              type="color"
              value={selectedConfig.color}
              onChange={(e) => updatePlaceholderProperties('fill', e.target.value)}
            />
          </label>

          <label>
            Text Align:
            <select
              value={selectedConfig.textAlign}
              onChange={(e) => updatePlaceholderProperties('textAlign', e.target.value)}
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </label>
        </div>
      )}

      <button onClick={saveTemplate} className="save-button">
        Save Template
      </button>
    </div>
  );
};

export default CertificateTemplateDesigner;
```

## Vue 3 + Fabric.js Example

```vue
<template>
  <div class="template-designer">
    <div class="toolbar">
      <input
        type="file"
        accept="image/jpeg,image/png"
        @change="handleImageUpload"
      />
      <button @click="addPlaceholder('student_name')">Add Student Name</button>
      <button @click="addPlaceholder('course_name')">Add Course Name</button>
      <button @click="addPlaceholder('date')">Add Date</button>
    </div>

    <div class="canvas-container">
      <canvas ref="canvasRef"></canvas>
    </div>

    <div v-if="selectedPlaceholder" class="properties-panel">
      <h3>Properties: {{ selectedPlaceholder }}</h3>
      <!-- Property controls similar to React example -->
    </div>

    <button @click="saveTemplate" class="save-button">Save Template</button>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { fabric } from 'fabric';
import axios from 'axios';

const props = defineProps({
  templateId: Number,
});

const canvasRef = ref(null);
const canvas = ref(null);
const backgroundImage = ref(null);
const placeholders = ref([]);
const selectedPlaceholder = ref(null);

onMounted(() => {
  canvas.value = new fabric.Canvas(canvasRef.value, {
    width: 1200,
    height: 848,
    backgroundColor: '#ffffff',
  });

  if (props.templateId) {
    loadTemplate();
  }

  // Event listeners (similar to React example)
});

onUnmounted(() => {
  if (canvas.value) {
    canvas.value.dispose();
  }
});

// Methods similar to React example
const handleImageUpload = (event) => { /* ... */ };
const addPlaceholder = (variableName) => { /* ... */ };
const saveTemplate = async () => { /* ... */ };
const loadTemplate = async () => { /* ... */ };
</script>
```

## Key Points

1. **Coordinates as Percentages**: Always convert pixel coordinates to percentages:
   ```javascript
   x: object.left / canvas.width,
   y: object.top / canvas.height
   ```

2. **Aspect Ratio**: Maintain aspect ratio when loading background images

3. **Font Consistency**: Use the same fonts available on the backend

4. **Variable Naming**: Store variable names as metadata on canvas objects

5. **Real-time Updates**: Update placeholder list when objects are moved/modified

## Styling Recommendations

```css
.template-designer {
  display: flex;
  flex-direction: column;
  height: 100vh;
}

.canvas-container {
  flex: 1;
  overflow: auto;
  background: #f0f0f0;
  display: flex;
  justify-content: center;
  align-items: center;
}

.properties-panel {
  position: fixed;
  right: 0;
  top: 0;
  width: 300px;
  background: white;
  padding: 20px;
  box-shadow: -2px 0 5px rgba(0,0,0,0.1);
  height: 100vh;
  overflow-y: auto;
}

.toolbar {
  padding: 10px;
  background: #fff;
  border-bottom: 1px solid #ddd;
  display: flex;
  gap: 10px;
}
```

