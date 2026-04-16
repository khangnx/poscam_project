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

def generate_engine_noise(filename, duration=1.0, base_freq=60, harmonic_ratio=1.5, noise_level=0.2):
    import wave
    import struct
    import math
    import random
    
    sample_rate = 44100
    n_samples = int(sample_rate * duration)
    
    with wave.open(filename, 'w') as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(sample_rate)
        
        for i in range(n_samples):
            t = i / sample_rate
            
            # Layer 1: Base rumble (Sine)
            val = 0.5 * math.sin(2 * math.pi * base_freq * t)
            
            # Layer 2: Harmonic (Square-ish for grit)
            val += 0.3 * (1 if math.sin(2 * math.pi * base_freq * harmonic_ratio * t) > 0 else -1)
            
            # Layer 3: Combustion Noise
            val += noise_level * random.uniform(-1, 1)
            
            # Add a "pulse" feel (RPM simulation)
            pulse = 0.8 + 0.2 * math.sin(2 * math.pi * (base_freq/10) * t)
            val *= pulse
            
            # Fade in/out to avoid clicks
            fade = 1.0
            if i < 2000: fade = i / 2000
            if i > n_samples - 2000: fade = (n_samples - i) / 2000
            
            sample = int(max(-1, min(1, val)) * 32767 * 0.4 * fade) # 40% master volume
            f.writeframesraw(struct.pack('<h', sample))

def generate_collision_noise(filename, duration=0.6):
    import wave
    import struct
    import random
    
    sample_rate = 44100
    n_samples = int(sample_rate * duration)
    
    with wave.open(filename, 'w') as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(sample_rate)
        
        for i in range(n_samples):
            # Rapid decay noise
            decay = (1.0 - i/n_samples) ** 2
            # High intensity white noise + some low frequency impact
            val = 0.7 * random.uniform(-1, 1) * decay
            val += 0.3 * random.choice([-1, 1]) * decay # Low-end thud
            
            sample = int(max(-1, min(1, val)) * 32767 * 0.8)
            f.writeframesraw(struct.pack('<h', sample))

def setup_assets():
    try:
        pygame.init()
        # Try to init display hidden just for surface creation if needed
        try:
            pygame.display.set_mode((1, 1), pygame.HIDDEN)
        except:
            print("Assets Helper: Running in display-less mode")
    except Exception as e:
        print(f"Assets Helper: Pygame init failed: {e}")
    
    assets_dir = os.path.join(os.path.dirname(__file__), 'assets')
    os.makedirs(assets_dir, exist_ok=True)
    
    # Image assets
    car_path = os.path.join(assets_dir, 'car.png')
    obs_path = os.path.join(assets_dir, 'obstacle.png')
    finish_path = os.path.join(assets_dir, 'finish.png')
    
    if not os.path.exists(car_path): create_car(car_path)
    if not os.path.exists(obs_path): create_obstacle(obs_path)
    if not os.path.exists(finish_path): create_finish_line(finish_path)
    
    # Sound assets (New Dynamic System)
    # 1. Idle Sound (low freq, steady)
    idle_path = os.path.join(assets_dir, 'engine_idle.wav')
    if not os.path.exists(idle_path):
        print(f"Generating {idle_path}...")
        generate_engine_noise(idle_path, duration=2.0, base_freq=50, harmonic_ratio=1.2, noise_level=0.1)
        
    # 2. Race Sound (higher freq, aggressive)
    race_path = os.path.join(assets_dir, 'engine_race.wav')
    if not os.path.exists(race_path):
        print(f"Generating {race_path}...")
        generate_engine_noise(race_path, duration=1.0, base_freq=120, harmonic_ratio=1.8, noise_level=0.3)
        
    # 3. Collision Sound
    col_path = os.path.join(assets_dir, 'collision.wav')
    if not os.path.exists(col_path):
        print(f"Generating {col_path}...")
        generate_collision_noise(col_path, duration=0.8)
    
    # Legacy fallbacks/placeholders if needed by other components
    menu_path = os.path.join(assets_dir, 'menu.wav')
    if not os.path.exists(menu_path):
        generate_beep(menu_path, duration=2.0, freq=330, type='sine')
        
    print("Assets setup complete!")
    pygame.quit()

if __name__ == '__main__':
    setup_assets()

if __name__ == '__main__':
    setup_assets()
