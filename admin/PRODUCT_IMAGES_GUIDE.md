# 🖼️ CafeNIX Product Images Guide

## 📋 Overview
CafeNIX supports both single product images and multiple gallery images to showcase your menu items effectively.

## 🎯 Image Types

### 1. Main Product Image (Screenshot)
- **Purpose**: Primary thumbnail displayed in product listings
- **Location**: `uploads/screenshots/` directory
- **Field**: "Product Image" in admin form
- **Display**: Product cards, search results, category pages

### 2. Gallery Images
- **Purpose**: Multiple images to showcase menu items from different angles
- **Location**: `uploads/gallery/` directory  
- **Field**: "Product Gallery" in admin form
- **Display**: Product detail pages (future enhancement)

## 📤 How to Add Images

### Adding a New Product

1. **Fill Product Details**
   - Name, description, price, category
   - INR pricing (₹ symbol)

2. **Upload Main Image**
   - Click "Choose File" under "Product Image"
   - Select high-quality image (JPG, PNG, GIF)
   - Recommended size: 800x600px or larger
   - File will be shown as preview

3. **Upload Gallery Images** (Optional)
   - Click "Choose File" under "Product Gallery"
   - Select multiple images (hold Ctrl/Cmd)
   - All selected images will be uploaded
   - Images appear in gallery section

4. **Save Product**
   - Click "Add Menu Item"
   - All images saved automatically

### Editing Existing Product

1. **Open Edit Modal**
   - Click edit button on product list
   - Modal opens with current data

2. **View Current Images**
   - Main image shows as thumbnail preview
   - Gallery images show in grid layout
   - Each gallery image has delete button

3. **Update Images**
   - **Replace Main Image**: Upload new image (replaces old one)
   - **Add Gallery Images**: Select multiple new images
   - **Remove Gallery**: Click ❌ on individual gallery images

4. **Save Changes**
   - Click "Update Menu Item"
   - Changes saved and redirected to list

## 🎨 Image Guidelines

### Recommended Specifications

| Image Type | Size | Format | Quality |
|------------|------|--------|---------|
| Main Image | 800x600px | JPG/PNG | High quality |
| Gallery Images | 1200x900px | JPG/PNG | High quality |
| Thumbnails | Auto-generated | - | Optimized |

### Best Practices

1. **High Quality**: Use clear, professional photos
2. **Consistent Style**: Similar lighting and background
3. **Cafe Theme**: Images should match cafe atmosphere
4. **File Size**: Keep under 2MB per image
5. **Naming**: Use descriptive names (e.g., "cappuccino-latte.jpg")

## 📁 File Structure

```
uploads/
├── screenshots/     # Main product images
│   ├── cappuccino.jpg
│   ├── latte.png
│   └── ...
├── gallery/         # Multiple gallery images
│   ├── cappuccino-1.jpg
│   ├── cappuccino-2.jpg
│   └── ...
└── products/       # Downloadable files
    └── ...
```

## 🔧 Technical Details

### Database Tables

```sql
-- Products table (main image)
products.screenshot VARCHAR(255)

-- Gallery images table
product_images (
    id INT PRIMARY KEY,
    product_id INT,
    image_path VARCHAR(255),
    alt_text VARCHAR(200),
    sort_order INT
)
```

### Upload Process

1. **Validation**: File type, size checked
2. **Sanitization**: Filename cleaned for security
3. **Storage**: File saved to appropriate directory
4. **Database**: Path recorded in database
5. **Thumbnails**: Generated for display

## 🚀 Advanced Features

### Gallery Management

- **Multiple Upload**: Select many images at once
- **Individual Delete**: Remove specific gallery images
- **Sort Order**: Images display in upload order
- **Alt Text**: Automatically generated for SEO

### Image Display

- **Responsive**: Images adapt to screen size
- **Lazy Loading**: Improves page performance
- **Fallback**: Placeholder if no image
- **SEO Optimized**: Alt tags and proper naming

## 🛠️ Troubleshooting

### Common Issues

1. **Upload Fails**
   - Check file size (max 5MB)
   - Verify file type (JPG, PNG, GIF)
   - Ensure directory permissions

2. **Image Not Showing**
   - Verify file path in database
   - Check if file exists in directory
   - Clear browser cache

3. **Gallery Images Missing**
   - Check product_images table
   - Verify files in gallery directory
   - Check sort_order values

### Error Messages

- "File upload failed": Check file size and type
- "Invalid image format": Use JPG, PNG, or GIF
- "Upload directory error": Check folder permissions

## 📱 Mobile Considerations

- Images are responsive on mobile devices
- Gallery adapts to screen width
- Touch-friendly delete buttons
- Optimized loading for mobile networks

## 🔒 Security Features

- File type validation
- Size restrictions
- Path sanitization
- Database parameterization
- Access control (admin only)

---

## 🎉 Success Tips

1. **Professional Photos**: Invest in good product photography
2. **Multiple Angles**: Show products from different perspectives
3. **Consistent Background**: Use plain or cafe-themed backgrounds
4. **Proper Lighting**: Bright, clear images work best
5. **Regular Updates**: Refresh images to keep content fresh

With these guidelines, you can create an attractive, professional-looking cafe menu that showcases your products effectively!
