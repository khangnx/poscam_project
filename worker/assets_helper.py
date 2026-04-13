import os
import pygame

def create_car(filename):
    surface = pygame.Surface((40, 70), pygame.SRCALPHA)
    # Body
    pygame.draw.rect(surface, (41, 128, 185), (2, 5, 36, 60), border_radius=5)
    # Roof
    pygame.draw.rect(surface, (52, 152, 219), (8, 20, 24, 30), border_radius=3)
    # Tires
    pygame.draw.rect(surface, (30, 30, 30), (0, 10, 4, 15))
    pygame.draw.rect(surface, (30, 30, 30), (36, 10, 4, 15))
    pygame.draw.rect(surface, (30, 30, 30), (0, 45, 4, 15))
    pygame.draw.rect(surface, (30, 30, 30), (36, 45, 4, 15))
    pygame.image.save(surface, filename)

def create_obstacle(filename):
    surface = pygame.Surface((40, 40), pygame.SRCALPHA)
    # Cone
    pygame.draw.polygon(surface, (230, 126, 34), [(20, 5), (5, 35), (35, 35)])
    pygame.draw.polygon(surface, (211, 84, 0), [(20, 15), (10, 30), (30, 30)])
    pygame.image.save(surface, filename)

def create_finish_line(filename):
    surface = pygame.Surface((400, 40), pygame.SRCALPHA)
    surface.fill((255, 255, 255))
    # Checkerboard
    for y in range(0, 40, 20):
        for x in range(0, 400, 20):
            if (x // 20 + y // 20) % 2 == 0:
                pygame.draw.rect(surface, (0, 0, 0), (x, y, 20, 20))
    pygame.image.save(surface, filename)

import math
import wave
import struct

def generate_beep(filename, duration=1.0, freq=440, type='sine'):
    sample_rate = 44100
    n_samples = int(sample_rate * duration)
    
    with wave.open(filename, 'w') as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(sample_rate)
        
        for i in range(n_samples):
            t = i / sample_rate
            if type == 'sine':
                val = math.sin(2 * math.pi * freq * t)
            elif type == 'square':
                val = 1 if math.sin(2 * math.pi * freq * t) > 0 else -1
            
            # Fade in/out to avoid clicks
            fade = 1.0
            if i < 1000: fade = i / 1000
            if i > n_samples - 1000: fade = (n_samples - i) / 1000
            
            sample = int(val * 32767 * 0.3 * fade) # 30% volume
            f.writeframesraw(struct.pack('<h', sample))

def generate_noise(filename, duration=0.5, intensity=0.5):
    import random
    sample_rate = 44100
    n_samples = int(sample_rate * duration)
    
    with wave.open(filename, 'w') as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(sample_rate)
        
        for i in range(n_samples):
            val = random.uniform(-1, 1)
            # Decay for crash
            fade = (n_samples - i) / n_samples
            sample = int(val * 32767 * intensity * fade)
            f.writeframesraw(struct.pack('<h', sample))

def create_engine_sounds(assets_dir):
    variations = [
        ('engine_slow.wav', 150, 'sine'),
        ('engine_mid.wav', 250, 'sine'),
        ('engine_fast.wav', 400, 'square')
    ]
    for name, freq, type in variations:
        path = os.path.join(assets_dir, name)
        if not os.path.exists(path):
            generate_beep(path, duration=0.5, freq=freq, type=type)
            print(f"Created {path}")

def create_placeholder_music(assets_dir):
    menu_path = os.path.join(assets_dir, 'menu.wav')
    race_path = os.path.join(assets_dir, 'race.wav')
    
    if not os.path.exists(menu_path):
        # A calm slow pulse for menu
        generate_beep(menu_path, duration=2.0, freq=330, type='sine')
        print(f"Created {menu_path}")
        
    if not os.path.exists(race_path):
        # A more aggressive square wave pulse for racing
        generate_beep(race_path, duration=0.5, freq=220, type='square')
        print(f"Created {race_path}")

def setup_assets():
    pygame.init()
    pygame.display.set_mode((1, 1), pygame.HIDDEN)
    
    assets_dir = os.path.join(os.path.dirname(__file__), 'assets')
    os.makedirs(assets_dir, exist_ok=True)
    
    car_path = os.path.join(assets_dir, 'car.png')
    obs_path = os.path.join(assets_dir, 'obstacle.png')
    finish_path = os.path.join(assets_dir, 'finish.png')
    crash_path = os.path.join(assets_dir, 'crash.wav')
    
    if not os.path.exists(car_path): create_car(car_path)
    if not os.path.exists(obs_path): create_obstacle(obs_path)
    if not os.path.exists(finish_path): create_finish_line(finish_path)
    
    if not os.path.exists(crash_path):
        generate_noise(crash_path, duration=0.4, intensity=0.6)
        print(f"Created {crash_path}")
        
    create_engine_sounds(assets_dir)
    create_placeholder_music(assets_dir)
    
    print("Assets generated successfully!")
    pygame.quit()

if __name__ == '__main__':
    setup_assets()
