
import time
import os
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

# Configuration
SURVEY_URL = "https://ek.eco/survey-baseline"
DUMMY_IMAGE_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), "dummy.jpg"))

def setup_driver():
    options = webdriver.ChromeOptions()
    options.add_argument("--start-maximized")
    # options.add_argument("--headless") # Uncomment to run headless
    
    # Handle geolocation permission automatically (optional, but good for testing)
    prefs = {"profile.default_content_setting_values.geolocation": 1} # 1:allow, 2:block
    options.add_experimental_option("prefs", prefs)
    
    # Use Webdriver Manager to handle driver installation
    service = Service(ChromeDriverManager().install())
    driver = webdriver.Chrome(service=service, options=options)
    return driver

def fill_visible_fields(driver):
    """Fills all visible inputs in the current active section."""
    
    # Find active section
    try:
        active_section = driver.find_element(By.CSS_SELECTOR, ".ek-survey-section.active")
    except:
        print("No active section found.")
        return

    # 1. Text, Email, Number, Date Inputs
    inputs = active_section.find_elements(By.CSS_SELECTOR, "input[type='text'], input[type='email'], input[type='number'], input[type='date']")
    for inp in inputs:
        if not inp.is_displayed():
            continue
            
        # Skip if already filled (e.g. from previous attempts or default)
        if inp.get_attribute("value"):
            continue

        # Check if it is Geolocation (readonly) or Date or Standard
        parent = inp.find_element(By.XPATH, "./..")
        
        if "ek-geo-wrapper" in parent.get_attribute("class"):
            # Geolocation: Inspect the validation - logic expects value in input
            # Method 2 - Inject value directly (Faster/More reliable for automation)
            driver.execute_script("arguments[0].value = '12.34, 56.78';", inp)
            print(f"Filled Geolocation: {inp.get_attribute('name')}")
            
        elif inp.get_attribute("type") == "date":
             # Date Input: specific format required YYYY-MM-DD
             driver.execute_script("arguments[0].value = '2024-01-01';", inp)
             print(f"Filled Date: {inp.get_attribute('name')}")

        elif inp.get_attribute("type") == "number":
             # Ensure only numbers are sent
             driver.execute_script("arguments[0].value = '42';", inp)
             print(f"Filled Number: {inp.get_attribute('name')}")

        elif inp.get_attribute("type") == "email":
             inp.send_keys("test@example.com")
             print(f"Filled Email: {inp.get_attribute('name')}")

        elif "ek-other-input" in inp.get_attribute("class"):
             inp.send_keys("Other specified value")
             print(f"Filled Other: {inp.get_attribute('name')}")
             
        else:
            # Standard Text
            inp.send_keys("Test Value")
            print(f"Filled Input: {inp.get_attribute('name')}")

    # 2. Radio Buttons
    # Group by name to ensure we click one per group
    radios = active_section.find_elements(By.CSS_SELECTOR, "input[type='radio']")
    seen_radios = set()
    for radio in radios:
        if not radio.is_displayed():
            continue
        name = radio.get_attribute("name")
        if name not in seen_radios:
            # Click the FIRST option in the group
            driver.execute_script("arguments[0].click();", radio) 
            seen_radios.add(name)
            print(f"Clicked Radio Group: {name}")

    # 3. Checkboxes
    checkboxes = active_section.find_elements(By.CSS_SELECTOR, "input[type='checkbox']")
    seen_checkboxes = set()
    for cb in checkboxes:
        if not cb.is_displayed():
            continue
        name = cb.get_attribute("name")
        if name not in seen_checkboxes:
             # Click first checkbox
            driver.execute_script("arguments[0].click();", cb)
            seen_checkboxes.add(name)
            print(f"Clicked Checkbox Group: {name}")

    # 4. File Uploads
    file_inputs = active_section.find_elements(By.CSS_SELECTOR, "input[type='file']")
    
    # Custom Image Path
    custom_image_path = r"C:\Users\Admin\Downloads\WhatsApp Image 2026-02-06 at 11.02.24 AM.jpeg"
    
    # Ensure it exists
    if os.path.exists(custom_image_path):
        valid_images = [custom_image_path]
        print(f"Using custom image: {custom_image_path}")
    else:
        print(f"Custom image not found at {custom_image_path}. Using fallbacks.")
        # Fallback to downloaded images
        img_dir = os.path.dirname(__file__)
        available_images = [os.path.abspath(os.path.join(img_dir, f)) for f in ["respondent.jpg", "household.jpg", "water.jpg"]]
        valid_images = [img for img in available_images if os.path.exists(img)]
        if not valid_images:
            valid_images = [DUMMY_IMAGE_PATH] # Final Fallback

    for i, finp in enumerate(file_inputs):
        if finp.get_attribute("value"):
            continue
            
        # Rotate through available images
        img_to_use = valid_images[i % len(valid_images)]
        finp.send_keys(img_to_use)
        print(f"Uploaded File: {finp.get_attribute('name')} -> {os.path.basename(img_to_use)}")

    # 5. Signatures
    canvases = active_section.find_elements(By.CSS_SELECTOR, "canvas.ek-signature-canvas")
    for canvas in canvases:
        if not canvas.is_displayed():
            continue
        # check if hidden input has value
        wrapper = canvas.find_element(By.XPATH, "./..")
        hidden = wrapper.find_element(By.CSS_SELECTOR, "input.ek-signature-input")
        if hidden.get_attribute("value"):
            continue
            
        # Scroll into view to ensure visibility
        driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", canvas)
        time.sleep(0.5)

        # Draw something
        actions = ActionChains(driver)
        actions.move_to_element(canvas).click_and_hold().move_by_offset(20, 20).move_by_offset(-20, 10).release().perform()
        
        # Manually trigger change or blur if needed, or rely on mouseup logic in JS
        # Force update hidden input just in case JS event didn't fire perfectly
        # driver.execute_script("arguments[0].value = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';", hidden)
        
        print("Signed Signature Pad")
        time.sleep(0.5)


def run_test():
    driver = setup_driver()
    try:
        print(f"Navigating to {SURVEY_URL}...")
        driver.get(SURVEY_URL)
        time.sleep(3) # Wait for load

        step = 1
        while True:
            print(f"--- Processing Step {step} ---")
            
            # Fill fields
            fill_visible_fields(driver)
            time.sleep(1)

            # Try Next
            next_btns = driver.find_elements(By.CSS_SELECTOR, ".ek-btn-next")
            visible_next = [b for b in next_btns if b.is_displayed()]
            
            # Try to remove Cookie banner if present (it blocks buttons)
            try:
                driver.execute_script("var element = document.getElementById('CybotCookiebotDialog'); if(element) { element.parentNode.removeChild(element); console.log('Removed Cookie Banner'); }")
            except:
                pass
                
            if visible_next:
                print("Clicking Next...")
                # Scroll to button to be safe
                driver.execute_script("arguments[0].scrollIntoView(true);", visible_next[0])
                time.sleep(0.5)
                visible_next[0].click()
                time.sleep(2) # Transition wait
                step += 1
            else:
                # content loaded? Check submit
                submit_btns = driver.find_elements(By.CSS_SELECTOR, ".ek-btn-submit")
                visible_submit = [b for b in submit_btns if b.is_displayed()]
                
                if visible_submit:
                    print("Clicking Submit...")
                    # Scroll to ensure visibility
                    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", visible_submit[0])
                    time.sleep(0.5)
                    visible_submit[0].click()
                    
                    print("Waiting for success message...")
                    # Wait for success
                    WebDriverWait(driver, 30).until(
                        EC.presence_of_element_located((By.CSS_SELECTOR, ".ek-success-message"))
                    )
                    print("\nSUCCESS! Survey Submitted.")
                    
                    # Click Download Button
                    try:
                        download_btn = driver.find_element(By.CSS_SELECTOR, ".ek-btn-download")
                        print("Found Download Button. Clicking...")
                        driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", download_btn)
                        time.sleep(1)
                        download_btn.click()
                        print("Download started. Waiting 40 seconds...")
                        time.sleep(40)
                    except Exception as e:
                        print(f"Could not click download button: {e}")
                    
                    break
                else:
                    print("No Next or Submit button found. Stuck?")
                    break

    except Exception as e:
        print(f"\nERROR: {e}")
    finally:
        print("Script finished. Browser will remain open for 120 seconds for verification...")
        time.sleep(120)
        driver.quit()

def download_sample_images():
    """Downloads sample images for testing."""
    images = [
        {"url": "https://via.placeholder.com/150/0000FF/808080?text=Respondent", "path": "respondent.jpg"},
        {"url": "https://via.placeholder.com/150/FF0000/FFFFFF?text=Household", "path": "household.jpg"},
        {"url": "https://via.placeholder.com/150/00FF00/000000?text=Water", "path": "water.jpg"}
    ]
    
    downloaded_paths = []
    import urllib.request
    
    for img in images:
        path = os.path.abspath(os.path.join(os.path.dirname(__file__), img["path"]))
        if not os.path.exists(path):
            print(f"Downloading {img['url']} to {path}...")
            try:
                # Add headers to avoid 403 Forbidden
                req = urllib.request.Request(img["url"], headers={'User-Agent': 'Mozilla/5.0'})
                with urllib.request.urlopen(req) as response, open(path, 'wb') as out_file:
                    out_file.write(response.read())
            except Exception as e:
                print(f"Failed to download {img['url']}: {e}")
                # Fallback to creating a dummy image if download fails
                with open(path, "wb") as f:
                     f.write(b"\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xDB\x00C\x00\xFF\xC0\x00\x11\x08\x00\x0A\x00\x0A\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\xFF\xDA\x00\x0C\x03\x01\x00\x02\x11\x03\x11\x00\x3F\x00\xBF\x00\xFF\xD9") 
        downloaded_paths.append(path)
    return downloaded_paths

if __name__ == "__main__":
    download_sample_images()
    run_test()
