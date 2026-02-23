
import time
import os
import random
import urllib.request
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.action_chains import ActionChains

# Configuration
URL = "https://ek.eco/survey-baseline" 
CUSTOM_IMAGE_PATH = r"C:\Users\Admin\Downloads\WhatsApp Image 2026-02-05 at 12.36.55 PM.jpeg"
DUMMY_IMAGE_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), "dummy.jpg"))

def setup_driver():
    options = webdriver.ChromeOptions()
    options.add_argument("--start-maximized")
    
    prefs = {"profile.default_content_setting_values.geolocation": 1}
    options.add_experimental_option("prefs", prefs)

    service = Service(ChromeDriverManager().install())
    driver = webdriver.Chrome(service=service, options=options)
    return driver

def set_network_conditions(driver, offline=False):
    if offline:
        driver.execute_cdp_cmd("Network.enable", {})
        driver.execute_cdp_cmd("Network.emulateNetworkConditions", {
            "offline": True,
            "latency": 0,
            "downloadThroughput": 0,
            "uploadThroughput": 0,
        })
        print("Network functionality disabled (Offline Mode).")
    else:
        driver.execute_cdp_cmd("Network.enable", {})
        driver.execute_cdp_cmd("Network.emulateNetworkConditions", {
            "offline": False,
            "latency": 0,
            "downloadThroughput": -1,
            "uploadThroughput": -1,
        })
        print("Network functionality enabled (Online Mode).")

def download_sample_images():
    if os.path.exists(CUSTOM_IMAGE_PATH):
        return [CUSTOM_IMAGE_PATH]

    images = [
        {"url": "https://via.placeholder.com/150/0000FF/808080?text=Respondent", "path": "respondent.jpg"},
        {"url": "https://via.placeholder.com/150/FF0000/FFFFFF?text=Household", "path": "household.jpg"},
    ]
    downloaded_paths = []
    for img in images:
        path = os.path.abspath(os.path.join(os.path.dirname(__file__), img["path"]))
        if not os.path.exists(path):
            try:
                req = urllib.request.Request(img["url"], headers={'User-Agent': 'Mozilla/5.0'})
                with urllib.request.urlopen(req) as response, open(path, 'wb') as out_file:
                    out_file.write(response.read())
            except Exception:
                if not os.path.exists(DUMMY_IMAGE_PATH):
                     with open(DUMMY_IMAGE_PATH, "wb") as f:
                         f.write(b"\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xDB\x00C\x00\xFF\xC0\x00\x11\x08\x00\x0A\x00\x0A\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\xFF\xDA\x00\x0C\x03\x01\x00\x02\x11\x03\x11\x00\x3F\x00\xBF\x00\xFF\xD9")
                path = DUMMY_IMAGE_PATH
        downloaded_paths.append(path)
    return downloaded_paths

def fill_visible_fields(driver, image_paths, overrides={}):
    try:
        active_section = driver.find_element(By.CSS_SELECTOR, ".ek-survey-section.active")
    except:
        return

    # 1. Inputs
    inputs = active_section.find_elements(By.CSS_SELECTOR, "input[type='text'], input[type='email'], input[type='number'], input[type='date']")
    for inp in inputs:
        if not inp.is_displayed() or inp.get_attribute("value"):
            continue

        name = inp.get_attribute("name")
        input_type = inp.get_attribute("type")
        parent_class = inp.find_element(By.XPATH, "./..").get_attribute("class")
        
        if "ek-geo-wrapper" in parent_class:
            driver.execute_script("arguments[0].value = '0.3476, 32.5825';", inp)
        elif input_type == "date":
             driver.execute_script("arguments[0].value = '2026-02-11';", inp)
        elif input_type == "number":
             val = str(random.randint(1, 10))
             inp.send_keys(val)
        elif input_type == "email":
             inp.send_keys(f"test{random.randint(100,999)}@example.com")
        elif "other" in name or "Other" in name: 
            inp.send_keys("Random Other Text")
        else:
            inp.send_keys("Test Value")

    # 2. Radios
    radios = active_section.find_elements(By.CSS_SELECTOR, "input[type='radio']")
    groups = {}
    for r in radios:
        if not r.is_displayed(): continue
        name = r.get_attribute("name")
        if name not in groups: groups[name] = []
        groups[name].append(r)

    for name, options in groups.items():
        if any(opt.is_selected() for opt in options): continue
        
        target = None
        if name in overrides:
            wanted_val = overrides[name]
            for opt in options:
                if wanted_val.lower() in opt.get_attribute("value").lower():
                    target = opt
                    break
        
        if not target:
             # Default yes for consent
            defaults = {"responses[1.1]": "Yes", "responses[1.2]": "Yes"}
            if name in defaults:
                wanted_val = defaults[name]
                for opt in options:
                    if wanted_val.lower() in opt.get_attribute("value").lower():
                        target = opt
                        break

        if not target:
            target = random.choice(options)
        
        driver.execute_script("arguments[0].click();", target)
        driver.execute_script("arguments[0].dispatchEvent(new Event('change', { bubbles: true }));", target)

    # 3. Checkboxes
    checkboxes = active_section.find_elements(By.CSS_SELECTOR, "input[type='checkbox']")
    cb_groups = {}
    for cb in checkboxes:
        if not cb.is_displayed(): continue
        name = cb.get_attribute("name")
        base_name = name
        if base_name not in cb_groups: cb_groups[base_name] = []
        cb_groups[base_name].append(cb)

    for name, options in cb_groups.items():
        if any(opt.is_selected() for opt in options): continue
        count = random.randint(1, len(options))
        targets = random.sample(options, count)
        for target in targets:
             driver.execute_script("arguments[0].click();", target)
             driver.execute_script("arguments[0].dispatchEvent(new Event('change', { bubbles: true }));", target)

    # 4. Files
    file_inputs = active_section.find_elements(By.CSS_SELECTOR, "input[type='file']")
    for i, finp in enumerate(file_inputs):
        if finp.get_attribute("value"): continue
        img_to_use = image_paths[i % len(image_paths)]
        finp.send_keys(img_to_use)

    # 5. Signatures
    canvases = active_section.find_elements(By.CSS_SELECTOR, "canvas.ek-signature-canvas")
    for canvas in canvases:
        if not canvas.is_displayed(): continue
        wrapper = canvas.find_element(By.XPATH, "./..")
        hidden = wrapper.find_element(By.CSS_SELECTOR, "input.ek-signature-input")
        if hidden.get_attribute("value"): continue
        
        driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", canvas)
        time.sleep(0.5)
        actions = ActionChains(driver)
        actions.move_to_element(canvas).click_and_hold().move_by_offset(20, 20).release().perform()
        driver.execute_script("arguments[0].dispatchEvent(new Event('mouseup', { bubbles: true }));", canvas)

def run_offline_test():
    driver = setup_driver()
    image_paths = download_sample_images()
    try:
        print("1. Opening Page Online to install Service Worker...")
        driver.get(URL)
        time.sleep(5) 


        # Wait for SW to be ready and controlling
        print("Waiting for Service Worker to activate and control the page...")
        for i in range(5):
            is_sw_active = driver.execute_script("return navigator.serviceWorker.controller !== null;")
            if is_sw_active:
                print("Service Worker is now controlling the page.")
                break
            
            if i == 0:
                 driver.refresh()
                 print("Refreshed page to claim clients.")
            
            time.sleep(2)
        else:
            print("WARNING: Service Worker did not take control. Test might fail.")

        # Extra wait for cache priming (fetch request in SW ready)
        time.sleep(3)

        print("2. Going Offline...")
        set_network_conditions(driver, offline=True)
        time.sleep(1)

        print("3. Refreshing Page (Offline Mode)...")
        try:
            driver.get(URL)
        except Exception as e:
            print(f"Navigation failed (expected if SW broken): {e}")
            # If navigation fails, we can't continue
            return

        time.sleep(2)

        
        try:
            driver.find_element(By.CSS_SELECTOR, ".ek-survey-container")
            print("SUCCESS: Survey container loaded offline!")
        except:
            print("FAILURE: Survey container NOT found offline.")
            return

        print("4. Filling Form (Offline Mode)...")
        step = 1
        max_steps = 30
        overrides = {"responses[7.2]": "Chlorine"}

        while step < max_steps:
             # Remove cookie banner
            try:
                driver.execute_script("var element = document.getElementById('CybotCookiebotDialog'); if(element) { element.parentNode.removeChild(element); }")
            except: pass

            fill_visible_fields(driver, image_paths, overrides)
            time.sleep(0.5)

            next_btns = driver.find_elements(By.CSS_SELECTOR, ".ek-btn-next")
            visible_next = [b for b in next_btns if b.is_displayed()]
            
            if visible_next:
                driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", visible_next[0])
                time.sleep(0.5)
                driver.execute_script("arguments[0].click();", visible_next[0])
                time.sleep(1)
                step += 1
            else:
                submit_btns = driver.find_elements(By.CSS_SELECTOR, ".ek-btn-submit")
                visible_submit = [b for b in submit_btns if b.is_displayed()]
                
                if visible_submit:
                    print(f"Submit button found at step {step}. Submitting Offline...")
                    driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", visible_submit[0])
                    time.sleep(0.5)
                    driver.execute_script("arguments[0].click();", visible_submit[0])
                    
                    print("Waiting for success message...")
                    try:
                        WebDriverWait(driver, 10).until(
                            EC.presence_of_element_located((By.CSS_SELECTOR, ".ek-success-message"))
                        )
                        success_msg = driver.find_element(By.CSS_SELECTOR, ".ek-success-message").text
                        print(f"Submission Result: {success_msg}")
                        
                        if "Saved Offline" in success_msg:
                            print("SUCCESS: Form saved offline correctly!")
                        else:
                            print("WARNING: Unexpected success message (maybe online?)")
                            
                    except TimeoutException:
                        print("FAILED: Timed out waiting for offline save confirmation.")
                    
                    break
                else:
                    errors = driver.find_elements(By.CSS_SELECTOR, ".ek-has-error")
                    if errors:
                        print("Validation Errors Found!")
                        # Debug: print first error
                        # print(errors[0].get_attribute('outerHTML'))
                    
                    if step > 25:
                        print("Stuck. Exiting loop.")
                        break

    except Exception as e:
        print(f"Test Error: {e}")
    finally:
        set_network_conditions(driver, offline=False)
        driver.quit()

if __name__ == "__main__":
    run_offline_test()
