import sys
import time
import pygame
import requests
import random
import os
import math
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Force dummy audio driver to avoid ALSA hangs in Docker
# SDL_AUDIODRIVER unset to allow real audio output (was 'dummy')

try:
    print("Mini Game: Setting up assets...", flush=True)
    import assets_helper
    assets_helper.setup_assets()
    print("Mini Game: Assets setup complete.", flush=True)
except Exception as e:
    print(f"Error setting up assets: {e}", flush=True)

try:
    print("Mini Game: Initializing Font...", flush=True)
    pygame.font.init()
    print("Mini Game: Font initialized.", flush=True)
except Exception as e:
    print(f"Font init failed: {e}", flush=True)

# Global variables for sound (to be initialized in play_game)
sounds = {}

def play_game(phone: str, tenant_id: str, customer_id: str = ""):
    print(f"Mini Game: Starting play_game for {phone}...", flush=True)
    
    # Initialize Mixer inside the main game function to avoid multiple instances issues
    try:
        print("Mini Game: Initializing Mixer...", flush=True)
        pygame.mixer.init()
        print("Mini Game: Mixer initialized.", flush=True)
    except Exception as e:
        print(f"Mixer init failed (Sound disabled): {e}", flush=True)

    try:
        pygame.init()
        print("Mini Game: Pygame.init() complete.", flush=True)
    except Exception as e:
        print(f"Pygame initialization warning: {e}", flush=True)

    WIDTH, HEIGHT = 400, 600
    headless = False
    try:
        # On Windows, we don't need DISPLAY. Juat attempt to set_mode.
        screen = pygame.display.set_mode((WIDTH, HEIGHT))
        pygame.display.set_caption("Mini Racing Game - Voucher & Challenge")
        print("Mini Game: Window opened successfully!", flush=True)
    except Exception as e:
        print(f"HEADLESS MODE ACTIVATED: Could not initialize display: {e}", flush=True)
        headless = True
        # Create a dummy surface so the rest of the code doesn't crash
        screen = pygame.Surface((WIDTH, HEIGHT))

    clock = pygame.time.Clock()
    
    # Colors Palette
    WHITE = (255, 255, 255)
    BLACK = (20, 20, 20)
    RED = (255, 60, 60)
    GREEN = (60, 255, 60)
    BLUE = (60, 120, 255)
    GRAY = (100, 100, 100)
    YELLOW = (255, 215, 0)
    GOLD = (212, 175, 55)
    GRASS_COLOR = (24, 119, 24)
    TRACK_COLOR = (45, 45, 45)
    DARK_VIGNETTE = (0, 0, 0, 150)
    
    # Procedural Car Drawing Function
    def create_car_surface(color, size=(40, 70)):
        surf = pygame.Surface(size, pygame.SRCALPHA)
        w, h = size
        # Main Body
        pygame.draw.rect(surf, color, (5, 10, w-10, h-20), border_radius=8)
        # Windows
        pygame.draw.rect(surf, (40, 40, 40), (8, 20, w-16, 12), border_radius=2) # Front
        pygame.draw.rect(surf, (40, 40, 40), (8, 45, w-16, 8), border_radius=2)  # Back
        # Wheels
        shell_color = (0, 0, 0)
        pygame.draw.rect(surf, shell_color, (0, 12, 6, 15), border_radius=2)
        pygame.draw.rect(surf, shell_color, (w-6, 12, 6, 15), border_radius=2)
        pygame.draw.rect(surf, shell_color, (0, h-27, 6, 15), border_radius=2)
        pygame.draw.rect(surf, shell_color, (w-6, h-27, 6, 15), border_radius=2)
        # Headlights
        pygame.draw.circle(surf, YELLOW, (12, 12), 3)
        pygame.draw.circle(surf, YELLOW, (w-12, 12), 3)
        return surf

    # Load Assets
    base_dir = os.path.dirname(__file__)
    assets_dir = os.path.join(base_dir, 'assets')
    
    # Sounds (with failure handling)
    def stop_all_sounds():
        try:
            pygame.mixer.stop()
            pygame.mixer.music.stop()
        except: pass

    def play_music(loop=-1):
        try: pygame.mixer.music.play(loop)
        except: pass

    def stop_music():
        try: pygame.mixer.music.stop()
        except: pass

    try:
        car_img = pygame.image.load(os.path.join(assets_dir, 'car.png')).convert_alpha()
    except:
        car_img = create_car_surface(BLUE)
        
    try:
        finish_img = pygame.image.load(os.path.join(assets_dir, 'finish.png')).convert_alpha()
    except:
        finish_img = pygame.Surface((WIDTH, 40))
        finish_img.fill(WHITE)
        for i in range(0, WIDTH, 40):
            pygame.draw.rect(finish_img, BLACK, (i, 0, 20, 20))
            pygame.draw.rect(finish_img, BLACK, (i+20, 20, 20, 20))

    car_mask = pygame.mask.from_surface(car_img)
    car_width, car_height = car_img.get_width(), car_img.get_height()
    
    # Fonts
    sys_font_name = "Segoe UI" if pygame.font.match_font("segoeui") else "Arial"
    font = pygame.font.SysFont(sys_font_name, 24, bold=True)
    big_font = pygame.font.SysFont(sys_font_name, 48, bold=True)
    menu_font = pygame.font.SysFont(sys_font_name, 22)
    small_font = pygame.font.SysFont(sys_font_name, 14) # Reduced from 16
    
    # Game State Variables
    state = "MENU"
    selected_speed = 1 # 0: Slow, 1: Medium, 2: Fast
    selected_density = 1 # 0: Low, 1: Medium, 2: High
    menu_error = ""
    
    # Difficulty Settings
    SPEED_VALUES = [300.0, 500.0, 750.0]
    DENSITY_VALUES = [1.2, 0.8, 0.5] # Delay between spawns
    DIFFICULTY_LABELS = ["CHẬM", "VỪA", "NHANH"]
    DENSITY_LABELS = ["ÍT", "BÌNH THƯỜNG", "ĐÔNG"]
    
    # Gameplay Variables
    car_x = WIDTH / 2 - car_width / 2
    car_y = HEIGHT - 90.0
    velocity = 0.0
    acceleration_rate = 2000.0
    friction = 1200.0 
    max_speed = 500.0
    
    grass_width = 40
    track_left = grass_width
    track_right = WIDTH - grass_width - car_width
    
    obstacles = []
    enemy_colors = [RED, GREEN, YELLOW, (200, 200, 200), (255, 127, 80), (147, 112, 219)]
    enemy_surfaces = {c: create_car_surface(c) for c in enemy_colors}
    enemy_masks = {c: pygame.mask.from_surface(surf) for c, surf in enemy_surfaces.items()}

    def spawn_obstacle(base_speed):
        color = random.choice(enemy_colors)
        return {
            'x': random.uniform(track_left, track_right),
            'y': -100.0,
            'speed': base_speed,
            'color': color
        }
    
    particles = []
    bg_y = 0.0
    bg_speed = 400.0
    
    countdown_timer = 0
    countdown_val = 3
    start_time = 0
    WIN_DURATION = 30
    score = 0
    camera_shake = 0.0
    voucher_called = False
    lose_time = 0.0
    win_time = 0.0
    last_spawn_time = 0

    # Load System Sounds
    global sounds
    sounds = {}
    for sname in ['engine_idle', 'engine_race', 'collision']:
        try: 
            sounds[sname] = pygame.mixer.Sound(os.path.join(assets_dir, f"{sname}.wav"))
        except: 
            sounds[sname] = None
    
    # Audio State Logic
    def play_menu_audio():
        try:
            if sounds['engine_idle'] and pygame.mixer.get_init():
                sounds['engine_idle'].set_volume(0.3)
                sounds['engine_idle'].play(-1) # Loop
        except: pass

    def play_race_audio():
        try:
            if pygame.mixer.get_init():
                if sounds['engine_idle']: sounds['engine_idle'].fadeout(500)
                if sounds['engine_race']:
                    sounds['engine_race'].set_volume(0.6)
                    sounds['engine_race'].play(-1)
        except: pass

    def play_collision_audio():
        try:
            if pygame.mixer.get_init():
                if sounds['engine_race']: sounds['engine_race'].stop()
                if sounds['collision']: sounds['collision'].play()
        except: pass

    def fade_out_all(ms=1000):
        try:
            if pygame.mixer.get_init():
                pygame.mixer.fadeout(ms)
        except: pass

    # Initial Menu Audio
    play_menu_audio()

    frame_count = 0
    running = True
    while running:
        frame_count += 1
        if frame_count % 100 == 0:
            print(f"Mini Game Heartsbeat: frame {frame_count}, state {state}, headless {headless}", flush=True)
        if headless:
            # Trong chế độ headless (khi chạy trong Docker không có Display), 
            # ta tự động chuyển sang trạng thái WIN để đảm bảo khách vẫn nhận được Voucher.
            print("Auto-triggering WIN state due to headless mode...")
            state = "WIN"
            win_time = time.time()
            dt = 0.016 # Giả lập 60fps
        else:
            dt = clock.tick(60) / 1000.0
            if dt > 0.1: dt = 0.1
            
        mouse_pos = pygame.mouse.get_pos()
        mouse_clicked = False
        
        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                running = False
                state = "CLOSED"
            if event.type == pygame.MOUSEBUTTONDOWN:
                mouse_clicked = True
        
        keys = pygame.key.get_pressed()
        
        # --- LOGIC ---
        # Shared timing variables for rendering and logic safety
        elapsed = time.time() - start_time if start_time > 0 else 0
        time_left = WIN_DURATION - elapsed
        fy = -999.0
        if time_left <= 2 and start_time > 0:
            fy = HEIGHT - (time_left / 2.0) * HEIGHT

        if state == "MENU":
            # Draw blurred background feel (just a static track)
            screen.fill(GRASS_COLOR)
            pygame.draw.rect(screen, (40, 40, 40), (track_left, 0, WIDTH - 2*grass_width, HEIGHT))
            
            overlay = pygame.Surface((WIDTH, HEIGHT), pygame.SRCALPHA)
            overlay.fill((0, 0, 0, 180))
            screen.blit(overlay, (0, 0))
            
            # Menu Title
            title = big_font.render("RACING MENU", True, WHITE)
            screen.blit(title, (WIDTH//2 - title.get_width()//2, 80))
            
            # Settings UI
            y_offset = 200
            
            def draw_selector(label, options, current_idx, y):
                lbl = menu_font.render(label, True, GRAY)
                screen.blit(lbl, (WIDTH//2 - lbl.get_width()//2, y))
                
                # Draw options
                opt_x = 55
                for i, opt in enumerate(options):
                    is_hover = pygame.Rect(opt_x, y + 40, 90, 35).collidepoint(mouse_pos)
                    bg_color = (80, 80, 200) if i == current_idx else ((60, 60, 60) if is_hover else (30, 30, 30))
                    pygame.draw.rect(screen, bg_color, (opt_x, y + 40, 90, 35), border_radius=5)
                    
                    txt = small_font.render(opt, True, WHITE)
                    screen.blit(txt, (opt_x + 45 - txt.get_width()//2, y + 57 - txt.get_height()//2))
                    
                    if is_hover and mouse_clicked:
                        return i
                    opt_x += 100
                return current_idx

            selected_speed = draw_selector("TỐC ĐỘ XE", DIFFICULTY_LABELS, selected_speed, 200)
            selected_density = draw_selector("MẬT ĐỘ GIAO THÔNG", DENSITY_LABELS, selected_density, 320) # More padding
            
            # Start Button with pulse effect
            pulse = (pygame.time.get_ticks() / 200)
            pulse_val = 150 + 105 * abs(math.sin(pulse))
            btn_color = (0, pulse_val, 0)
            
            btn_rect = pygame.Rect(WIDTH//2 - 100, 480, 200, 60)
            btn_hover = btn_rect.collidepoint(mouse_pos)
            
            # Shadow
            pygame.draw.rect(screen, (0, 40, 0), (btn_rect.x+4, btn_rect.y+4, 200, 60), border_radius=10)
            pygame.draw.rect(screen, GREEN if btn_hover else btn_color, btn_rect, border_radius=10)
            
            btn_txt = font.render("BẮT ĐẦU CHƠI", True, BLACK)
            screen.blit(btn_txt, (WIDTH//2 - btn_txt.get_width()//2, 510 - btn_txt.get_height()//2))
            
            if menu_error:
                err_txt = small_font.render(menu_error, True, RED)
                screen.blit(err_txt, (WIDTH//2 - err_txt.get_width()//2, 450))

            if btn_hover and mouse_clicked:
                # Challenge Mode Validation
                if selected_speed == 0 or selected_density == 0:
                    menu_error = "Mức độ này không đủ điều kiện nhận Voucher, hãy tăng độ khó!"
                else:
                    state = "COUNTDOWN"
                    countdown_val = 3
                    countdown_timer = 1.0
                    menu_error = ""
                    # Transition to race
                    play_race_audio()
                    # Setup gameplay vars based on config
                    max_speed = SPEED_VALUES[selected_speed]

        elif state == "COUNTDOWN":
            screen.fill(GRASS_COLOR)
            pygame.draw.rect(screen, TRACK_COLOR, (track_left, 0, WIDTH - 2*grass_width, HEIGHT))
            screen.blit(car_img, (car_x, car_y))
            
            overlay = pygame.Surface((WIDTH, HEIGHT), pygame.SRCALPHA)
            overlay.fill((0, 0, 0, 100))
            screen.blit(overlay, (0, 0))
            
            cd_txt = big_font.render(str(countdown_val) if countdown_val > 0 else "BẮT ĐẦU!", True, YELLOW)
            screen.blit(cd_txt, (WIDTH//2 - cd_txt.get_width()//2, HEIGHT//2 - cd_txt.get_height()//2))
            
            countdown_timer -= dt
            if countdown_timer <= 0:
                countdown_val -= 1
                countdown_timer = 1.0
                if countdown_val < 0:
                    state = "PLAYING"
                    start_time = time.time()

        elif state == "PLAYING":
            # Acceleration
            acc = 0.0
            if keys[pygame.K_LEFT]: acc -= acceleration_rate
            if keys[pygame.K_RIGHT]: acc += acceleration_rate
            
            velocity += acc * dt
            if acc == 0.0:
                if velocity > 0:
                    velocity -= friction * dt
                    if velocity < 0: velocity = 0
                elif velocity < 0:
                    velocity += friction * dt
                    if velocity > 0: velocity = 0
            
            velocity = max(-max_speed, min(velocity, max_speed))
            car_x += velocity * dt
            
            # Constraints
            if car_x < track_left:
                car_x = track_left
                velocity = 0
            elif car_x > track_right:
                car_x = track_right
                velocity = 0

            # Victory condition (using pre-calculated fy and time_left)
            if time_left <= 2 and fy >= car_y:
                state = "WIN"
                win_time = time.time()
                fade_out_all(1000)
                continue # Skip obstacles collision if already won

            # Obstacle Spawning (STOP spawning when finish line appears)
            is_hardcore = (selected_speed == 2 and selected_density == 2)
            spawn_delay = DENSITY_VALUES[selected_density] * (0.8 if is_hardcore else 1.0)
            
            if time.time() - last_spawn_time > spawn_delay and time_left > 2:
                obs = spawn_obstacle(400 + (elapsed * 10))
                if is_hardcore:
                    obs['zigzag'] = random.uniform(2, 4)
                    obs['offset'] = random.uniform(0, 6.28)
                obstacles.append(obs)
                last_spawn_time = time.time()
                
            # Obstacles Update
            for obs in obstacles[:]:
                obs['y'] += obs['speed'] * dt
                if obs.get('zigzag'):
                    obs['x'] += math.sin(time.time() * obs['zigzag'] + obs['offset']) * 2.0
                
                if obs['y'] > HEIGHT:
                    obstacles.remove(obs)
                    score += 1
                
                # Collision with forgiving hitbox (10% smaller)
                # Only check collision if NOT in WIN state (additional safety)
                if state == "PLAYING":
                    car_rect = pygame.Rect(car_x, car_y, car_width, car_height).inflate(- car_width*0.1, - car_height*0.1)
                    obs_rect = pygame.Rect(obs['x'], obs['y'], car_width, car_height).inflate(- car_width*0.1, - car_height*0.1)
                    
                    if car_rect.colliderect(obs_rect):
                        state = "LOSE"
                        camera_shake = 0.4
                        lose_time = time.time()
                        play_collision_audio()

            # BG & Particles
            bg_y = (bg_y + bg_speed * dt) % 60
            if random.random() < 0.3:
                particles.append({'x': car_x + car_width/2, 'y': car_y + car_height, 'vx': random.uniform(-10, 10), 'vy': random.uniform(20, 80), 'life': 0.3})
            for p in particles[:]:
                p['y'] += p['vy'] * dt
                p['life'] -= dt
                if p['life'] <= 0: particles.remove(p)

            # Racing Sound Pitch/Volume dynamics
            if sounds['engine_race'] and pygame.mixer.get_init():
                try:
                    # Simulate pitch/intensity by adjusting volume slightly based on "speed" 
                    # (or elapsed time as a proxy for intensity)
                    intensity = 0.6 + 0.4 * (elapsed / WIN_DURATION)
                    sounds['engine_race'].set_volume(min(1.0, intensity))
                except: pass

            # Timer (Handled above, but keeping for logic consistency if needed elsewhere)
            if time_left <= 0:
                state = "WIN"
                win_time = time.time()
                fade_out_all(1000)

        # --- RENDER (PLAYING/WIN/LOSE) ---
        if state in ["PLAYING", "LOSE", "WIN"]:
            shake_x, shake_y = 0, 0
            if state == "LOSE" and camera_shake > 0:
                camera_shake -= dt
                shake_x = random.randint(-5, 5)
                shake_y = random.randint(-5, 5)

            screen.fill(GRASS_COLOR)
            pygame.draw.rect(screen, TRACK_COLOR, (track_left + shake_x, 0 + shake_y, WIDTH - 2*grass_width, HEIGHT))
            
            # Motion Blur (Z-indexed below cars)
            if state == "PLAYING":
                curr_elapsed = time.time() - start_time
                speed_ratio = curr_elapsed / WIN_DURATION
                if speed_ratio > 0.5:
                    num_lines = int(3 + speed_ratio * 5)
                    for _ in range(num_lines):
                        lx = random.choice([track_left + 10, WIDTH - grass_width - 15])
                        ly = random.randint(0, HEIGHT)
                        pygame.draw.line(screen, (200, 200, 200, 100), (lx, ly), (lx, ly + 40), 2)

            # Lines
            for y_pos in range(int(bg_y) - 60, HEIGHT, 60):
                pygame.draw.rect(screen, WHITE, (WIDTH//2 - 5 + shake_x, y_pos + shake_y, 10, 30))

            # Finish Line
            if state == "PLAYING" or state == "WIN":
                if time_left <= 2:
                    # Use the same fy calculated at logic start
                    screen.blit(finish_img, (shake_x, int(fy) + shake_y))

            # Obstacles
            for obs in obstacles:
                screen.blit(enemy_surfaces[obs['color']], (int(obs['x']) + shake_x, int(obs['y']) + shake_y))
            
            # Particles
            for p in particles:
                pygame.draw.circle(screen, (200, 200, 200), (int(p['x']) + shake_x, int(p['y']) + shake_y), 4)

            # Car
            screen.blit(car_img, (int(car_x) + shake_x, int(car_y) + shake_y))
            
            # UI
            if state == "PLAYING":
                t_str = f"Thời gian: {max(0, int(time_left)+1)}s"
                s_str = f"Điểm: {score}"
                screen.blit(font.render(t_str, True, WHITE), (10, 10))
                screen.blit(font.render(s_str, True, WHITE), (WIDTH - 120, 10))

        if state == "LOSE":
            overlay = pygame.Surface((WIDTH, HEIGHT), pygame.SRCALPHA)
            overlay.fill((0, 0, 0, 180))
            screen.blit(overlay, (0, 0))
            msg = big_font.render("BẠN THUA!", True, RED)
            screen.blit(msg, (WIDTH//2 - msg.get_width()//2, HEIGHT//2 - 50))
            if time.time() - lose_time > 3: running = False

        if state == "WIN":
            overlay = pygame.Surface((WIDTH, HEIGHT), pygame.SRCALPHA)
            overlay.fill((0, 0, 0, 180))
            screen.blit(overlay, (0, 0))
            msg = big_font.render("CHÚC MỪNG!", True, GREEN)
            screen.blit(msg, (WIDTH//2 - msg.get_width()//2, HEIGHT//2 - 80))
            sub1 = font.render("Bạn đã thắng thử thách!", True, WHITE)
            screen.blit(sub1, (WIDTH//2 - sub1.get_width()//2, HEIGHT//2 - 10))
            sub2 = small_font.render("Voucher 3% đã được lưu vào mã khách hàng của bạn!", True, YELLOW)
            screen.blit(sub2, (WIDTH//2 - sub2.get_width()//2, HEIGHT//2 + 30))
            
            if not voucher_called:
                voucher_called = True
                clean_phone = phone.strip()
                print(f"Game finished. Issuing voucher via API...")
                
                payload = {
                    "phone": clean_phone,
                    "customer_id": customer_id if customer_id and customer_id != "None" else None
                }
                # Use dynamic backend URL from environment
                backend_base = os.getenv("BACKEND_API_URL", "http://localhost:8000/api")
                voucher_url = f"{backend_base}/vouchers/issue"
                secret = os.getenv("WORKER_SECRET", "worker-secret-token")
                headers = {"X-Internal-Secret": secret}
                
                urls = [voucher_url]
                success = False

                for url in urls:
                    print(f"TRYING API: {url} ...")
                    try: 
                        resp = requests.post(url, json=payload, headers=headers, timeout=5)
                        print(f"DEBUG - Status: {resp.status_code}")
                        print(f"DEBUG - Response: {resp.text}")
                        
                        if resp.status_code == 200:
                            print(f"SUCCESS: Voucher issued via {url}")
                            success = True
                            break
                        else:
                            print(f"API returned error status: {resp.status_code}")
                    except Exception as e: 
                        print(f"FAIL: Could not reach {url}. Error: {e}")
                
                if not success:
                    print("CRITICAL: ALL API ENDPOINTS FAILED. Voucher was NOT saved.")
            
            if time.time() - win_time > 4: running = False

        if not headless:
            pygame.display.flip()
    
    pygame.quit()

if __name__ == "__main__":
    if len(sys.argv) > 2:
        phone_param = sys.argv[1]
        tenant_param = sys.argv[2]
        customer_id_param = sys.argv[3] if len(sys.argv) > 3 else ""
        play_game(phone_param, tenant_param, customer_id_param)
    else:
        print("Required phone and tenant_id arguments")
        sys.exit(1)
