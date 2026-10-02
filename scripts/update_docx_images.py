import zipfile
import os
import shutil

docx_path = "c:/xampp/htdocs/Fitrova/Fitrova documentation.docx"
temp_dir = "c:/xampp/htdocs/Fitrova/docs/temp_docx_unzipped"
figures_dir = "c:/xampp/htdocs/Fitrova/docs/figures"

name_map = {
    "cover_logo.png": "word/media/image1.png",
    "figure_4.6.1.1_home_dashboard.png": "word/media/image2.png",
    "figure_4.6.1.2_signup_verification.png": "word/media/image3.png",
    "figure_4.6.1.3_user_onboarding.png": "word/media/image4.png",
    "figure_4.6.1.4_ai_workout.png": "word/media/image5.png",
    "figure_4.6.1.5_nutrition_tracker.png": "word/media/image6.png",
    "figure_4.6.1.6_weight_history.png": "word/media/image7.png"
}

if not os.path.exists(docx_path):
    print(f"Error: {docx_path} does not exist.")
    exit(1)

# Clean and recreate temp unzipped directory
if os.path.exists(temp_dir):
    shutil.rmtree(temp_dir)
os.makedirs(temp_dir, exist_ok=True)

# 1. Unzip the docx file
print("Unzipping document...")
with zipfile.ZipFile(docx_path, 'r') as z:
    z.extractall(temp_dir)

# 2. Replace the media files with any new files found in docs/figures
updated_count = 0
for fig_name, media_rel_path in name_map.items():
    source_path = os.path.join(figures_dir, fig_name)
    target_path = os.path.join(temp_dir, media_rel_path)
    
    if os.path.exists(source_path):
        # Double check if size is different to avoid copying if it's the original template image
        # Original size of image4 (user onboarding) is 41392 bytes
        original_sizes = {
            "cover_logo.png": 74434,
            "figure_4.6.1.1_home_dashboard.png": 232530,
            "figure_4.6.1.2_signup_verification.png": 47326,
            "figure_4.6.1.3_user_onboarding.png": 41392,
            "figure_4.6.1.4_ai_workout.png": 58349,
            "figure_4.6.1.5_nutrition_tracker.png": 57502,
            "figure_4.6.1.6_weight_history.png": 47889
        }
        
        current_size = os.path.getsize(source_path)
        if current_size == original_sizes.get(fig_name):
            print(f"Skipping {fig_name} (image is still the original template placeholder)")
            continue
            
        shutil.copyfile(source_path, target_path)
        print(f"Updated media inside docx: {fig_name} -> {media_rel_path}")
        updated_count += 1

# 3. Zip the directory back into a docx file if any changes were made
if updated_count > 0:
    print("Repacking document...")
    # Remove original docx and zip the temp directory contents
    os.remove(docx_path)
    
    # We zip manually to avoid directory inclusion issues of make_archive
    with zipfile.ZipFile(docx_path, 'w', zipfile.ZIP_DEFLATED) as z_out:
        for root, dirs, files in os.walk(temp_dir):
            for file in files:
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, temp_dir)
                z_out.write(full_path, rel_path)
                
    print(f"Successfully updated {updated_count} screenshots in Fitrova documentation.docx!")
else:
    print("No custom screenshots were updated (all files in docs/figures match the original templates).")

# Cleanup temp files
shutil.rmtree(temp_dir)
print("Cleanup complete.")
