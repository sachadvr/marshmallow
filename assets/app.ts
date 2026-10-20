import { startStimulusApp } from 'vite-plugin-symfony/stimulus/helpers'
import './styles/main.css'
import { registerVueComponents } from './vueComponents'

registerVueComponents()
startStimulusApp()
