# How to Get Font Files for Certificate Generation

This guide explains how to obtain the required font files (.ttf) to upload to your project.

## Option 1: Extract Fonts from Windows (If you have Windows)

If you're developing on Windows, you can copy fonts directly from your system:

### Steps:

1. **Open Fonts Folder**:
   - Press `Win + R` (Windows key + R)
   - Type: `fonts` and press Enter
   - OR navigate to: `C:\Windows\Fonts\`

2. **Find the fonts you need**:
   - **Arial**: Look for `arial.ttf` or `Arial.ttf`
   - **Times New Roman**: Look for `times.ttf` or `times new roman.ttf`
   - **Courier New**: Look for `cour.ttf` or `Courier New.ttf`
   - **Verdana**: Look for `verdana.ttf`
   - **Georgia**: Look for `georgia.ttf`
   - **Tahoma**: Look for `tahoma.ttf`
   - **Trebuchet MS**: Look for `trebuc.ttf` or `Trebuchet MS.ttf`
   - **Impact**: Look for `impact.ttf`

3. **Copy fonts to your project**:
   ```bash
   # Create the directory first
   mkdir resources/fonts
   
   # Then copy files from C:\Windows\Fonts\ to resources/fonts/
   # You can do this via file explorer or command line
   ```

**Note**: Fonts in Windows Fonts folder might have different names or be in different formats. Look for `.ttf` files.

## Option 2: Download Free Alternative Fonts

### Google Fonts (Free & Open Source)

1. **Visit**: https://fonts.google.com

2. **Download similar fonts**:
   - **Arial alternative**: "Roboto", "Open Sans", "Lato"
   - **Times New Roman alternative**: "Lora", "Merriweather", "Crimson Text"
   - **Courier New alternative**: "Source Code Pro", "Courier Prime"
   - **Verdana alternative**: "Source Sans Pro", "PT Sans"
   - **Georgia alternative**: "Playfair Display", "Libre Baskerville"

3. **Download process**:
   - Search for the font
   - Click on the font
   - Click "Download family" button (top right)
   - Extract the ZIP file
   - Look for `.ttf` files in the extracted folder
   - Copy `.ttf` files to `resources/fonts/`

### Liberation Fonts (Free Alternative to Microsoft Fonts)

Liberation fonts are free, open-source fonts designed to be metric-compatible with Microsoft fonts.

1. **Download from**:
   - GitHub: https://github.com/liberationfonts/liberation-fonts/releases
   - Or use package manager if available

2. **Font mapping**:
   - Liberation Sans → Arial/Helvetica alternative
   - Liberation Serif → Times New Roman alternative
   - Liberation Mono → Courier New alternative

3. **Files to extract**:
   - `LiberationSans-Regular.ttf` (rename to `arial.ttf` if needed)
   - `LiberationSerif-Regular.ttf` (rename to `times.ttf` if needed)
   - `LiberationMono-Regular.ttf` (rename to `courier.ttf` if needed)

### DejaVu Fonts (Free & Comprehensive)

DejaVu fonts are excellent free alternatives:

1. **Download from**: https://dejavu-fonts.github.io/
   - Or: https://github.com/dejavu-fonts/dejavu-fonts/releases

2. **Files to use**:
   - `DejaVuSans.ttf` (good alternative for Arial, Verdana, Tahoma)
   - `DejaVuSerif.ttf` (good alternative for Times New Roman, Georgia)
   - `DejaVuSansMono.ttf` (good alternative for Courier New)

## Option 3: Use System Fonts on Your Development Machine

If you're on Windows/Mac and want to use the exact fonts:

### Windows:
```bash
# Fonts are located at:
C:\Windows\Fonts\

# Common font files:
# - arial.ttf (Arial)
# - arialbd.ttf (Arial Bold)
# - times.ttf (Times New Roman)
# - timesbd.ttf (Times New Roman Bold)
# - cour.ttf (Courier New)
# - verdana.ttf (Verdana)
# - verdanab.ttf (Verdana Bold)
# - georgia.ttf (Georgia)
# - georgiab.ttf (Georgia Bold)
# - tahoma.ttf (Tahoma)
# - tahomabd.ttf (Tahoma Bold)
# - trebuc.ttf (Trebuchet MS)
# - trebucbd.ttf (Trebuchet MS Bold)
# - impact.ttf (Impact)
```

### macOS:
```bash
# Fonts are located at:
/System/Library/Fonts/Supplemental/
/Library/Fonts/
~/Library/Fonts/

# Look for:
# - Arial.ttf
# - Times New Roman.ttf
# - Courier New.ttf
# - Verdana.ttf
# - Georgia.ttf
```

## Option 4: Font Squirrel (Free Commercial Fonts)

1. **Visit**: https://www.fontsquirrel.com
2. **Search** for the font you need
3. **Filter by**: "100% Free" or "Free for Commercial Use"
4. **Download** and extract `.ttf` files

## Recommended Font Setup for Production

For production servers (cPanel/Linux), I recommend using **Liberation Fonts** or **DejaVu Fonts** because:
- ✅ Free and open source
- ✅ No licensing issues
- ✅ Work well on Linux servers
- ✅ Good compatibility with Microsoft fonts

### Quick Setup with Liberation Fonts:

1. **Download Liberation Fonts**:
   ```bash
   # Option A: Download from GitHub
   # Visit: https://github.com/liberationfonts/liberation-fonts/releases
   # Download the latest release ZIP file
   
   # Option B: On Linux, install via package manager
   sudo yum install liberation-fonts  # CentOS/RHEL
   sudo apt-get install fonts-liberation  # Debian/Ubuntu
   ```

2. **Copy to your project**:
   ```bash
   # After extracting or installing, copy .ttf files:
   cp /usr/share/fonts/liberation/*.ttf resources/fonts/
   
   # Or manually copy from the downloaded ZIP
   ```

3. **Rename for compatibility** (optional):
   ```bash
   cd resources/fonts/
   cp LiberationSans-Regular.ttf arial.ttf
   cp LiberationSerif-Regular.ttf times.ttf
   cp LiberationMono-Regular.ttf courier.ttf
   ```

## Step-by-Step: Uploading Fonts to Your Project

1. **Create the fonts directory**:
   ```bash
   mkdir -p resources/fonts
   # OR
   mkdir -p storage/fonts
   ```

2. **Get font files** (choose one method above)

3. **Copy font files** to `resources/fonts/`:
   - Via FTP/SFTP to your server
   - Via cPanel File Manager
   - Via Git (if you include fonts in your repository)
   - Via command line: `cp fontfile.ttf resources/fonts/`

4. **Set permissions** (on Linux/cPanel):
   ```bash
   chmod 644 resources/fonts/*.ttf
   ```

5. **Verify files are in place**:
   ```bash
   ls -la resources/fonts/
   # You should see your .ttf files
   ```

## Required Fonts for Certificate System

Minimum fonts needed:
- ✅ `arial.ttf` (for Arial, Helvetica)
- ✅ `times.ttf` (for Times New Roman)
- ✅ `courier.ttf` or `cour.ttf` (for Courier New)
- ✅ `verdana.ttf` (for Verdana)
- ✅ `georgia.ttf` (for Georgia)
- ✅ `tahoma.ttf` (for Tahoma)
- ✅ `trebuchet.ttf` or `trebuc.ttf` (for Trebuchet MS)
- ✅ `impact.ttf` (for Impact)

## Font File Naming

The system checks multiple filename variations:
- Lowercase: `arial.ttf`, `verdana.ttf`
- Proper case: `Arial.ttf`, `Verdana.ttf`
- Uppercase: `ARIAL.TTF`, `VERDANA.TTF`
- With hyphens: `times-new-roman.ttf`, `trebuchet-ms.ttf`

## Legal Considerations

⚠️ **Important**: Make sure you have the right to use fonts:

- **System fonts** (Windows/Mac): Usually OK for personal/internal use, but check Microsoft/Apple licensing for server use
- **Google Fonts**: 100% free, open source, commercial use OK
- **Liberation Fonts**: Free, open source, commercial use OK
- **DejaVu Fonts**: Free, open source, commercial use OK
- **Commercial fonts**: Require proper licensing for server/production use

**Recommendation**: Use open-source fonts (Google Fonts, Liberation, DejaVu) for production to avoid licensing issues.

## Quick Start (Recommended)

**Fastest way to get started:**

1. Download Liberation Fonts from GitHub:
   ```
   https://github.com/liberationfonts/liberation-fonts/releases
   ```

2. Extract the ZIP file

3. Copy these files to `resources/fonts/`:
   - `LiberationSans-Regular.ttf` → rename to `arial.ttf`
   - `LiberationSerif-Regular.ttf` → rename to `times.ttf`
   - `LiberationMono-Regular.ttf` → rename to `courier.ttf`

4. For other fonts, use DejaVu or download from Google Fonts

This gives you a solid foundation with free, legal fonts that work on all servers!


