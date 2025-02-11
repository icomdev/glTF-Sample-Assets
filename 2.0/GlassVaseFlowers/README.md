# GlassVaseFlowers

## Screenshot

![screenshot](screenshot/screenshot_large.jpg)

Screenshot from the [glTF Sample Viewer](https://github.khronos.org/glTF-Sample-Viewer-Release/) using the Cannon Exterior environment and the ACES tone mapper.

## Description

This model compares transparency methods for representing glass in glTF: [alphaMode:"BLEND"](https://registry.khronos.org/glTF/specs/2.0/glTF-2.0.html#_material_alphamode) (left) versus the extensions [KHR_materials_transmission](https://github.com/KhronosGroup/glTF/tree/main/extensions/2.0/Khronos/KHR_materials_transmission#readme) and [KHR_materials_volume](https://github.com/KhronosGroup/glTF/tree/main/extensions/2.0/Khronos/KHR_materials_volume#readme) (right). 

## Comparing Alpha Blending with Extensions

Before these extensions became available, alpha blending with a low alpha value in the `baseColorFactor` was used to represent refractive materials such as water or glass. 

However, alpha blending is designed to represent the visibility of a material, not refraction. Alpha can utilize a texture to reproduce the visibility of surfaces with small gaps (burlap or gauze) or complex edges (foliage) which can't reasonably be represented with triangles alone. This usually works best with pure white and pure black values.

When alpha blending is used with partial values, the surface shows more of what is behind, but in turn specular reflections are reduced. Alpha blending does not simulate refraction, nor diffusion, nor attenuation. Because of these limitations it is not recommended to use alpha blending for refractive materials.

Transmission and Volume are the recommended methods for reproducing refractive materials such as glass or water. These extensions allow light to transmit through a surface in a physically-plausible manner, reproducing effects like the bending of light through thick glass, colored transmission as in stained glass, color attenuation that can occur where surfaces become thicker, and the dispersion or blurring that can occur with roughened surfaces. 

## License Information

CC0 https://creativecommons.org/share-your-work/public-domain/cc0/. Vase by Eric Chadwick, flowers by Rico Cilliers.
