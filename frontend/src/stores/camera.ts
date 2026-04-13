import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Camera } from '@/types'

export const useCameraStore = defineStore('camera', () => {
  const cameras = ref<Camera[]>([])
  
  function setCameras(newCameras: Camera[]) {
    cameras.value = newCameras
  }

  function addCamera(camera: Camera) {
    cameras.value.push(camera)
  }

  return {
    cameras,
    setCameras,
    addCamera
  }
})
