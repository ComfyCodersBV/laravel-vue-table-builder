import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function toBoolean(value: any): boolean {
  if (typeof value === 'boolean') {
    return value
  }
  
  if (typeof value === 'string') {
    return ['true', '1', 'yes', 'on'].includes(value.toLowerCase())
  }
  
  if (typeof value === 'number') {
    return value === 1
  }
  
  return Boolean(value)
}
