# Event Image Uploads

This directory stores uploaded event images.

## Features:

✅ **Image Upload Support**
- Upload JPG, PNG, GIF, or WebP images for events
- Maximum file size: 5MB
- Images are automatically stored with timestamps

✅ **Image Management**
- View image preview before saving event
- Replace image when editing events
- Old images are automatically deleted when replaced

✅ **Display in Both Interfaces**
- Admin Dashboard: Shows image in event details modal
- Youth Portal: Displays image in event cards and modals

## File Structure:

```
assets/images/events/
├── event_1707200541_banner.jpg
├── event_1707200632_photo.png
└── ...
```

## Usage:

1. **Adding Event with Image:**
   - Go to Events Dashboard
   - Click "Add New Event"
   - Fill in event details
   - Select an image file
   - Click "Save Event"

2. **Editing Event Image:**
   - Click Edit on existing event
   - Select a new image (replaces the old one)
   - Click "Save Event"

3. **Removing Event Image:**
   - Edit the event
   - Don't select a new file
   - Deselect the current image if shown
   - Click "Save Event"

## Technical Details:

- Images are stored with timestamp + original filename for uniqueness
- Supported formats: JPEG, PNG, GIF, WebP
- Files are validated by MIME type (not extension)
- Automatic cleanup of old images when replaced
- Relative paths stored in database for portability
