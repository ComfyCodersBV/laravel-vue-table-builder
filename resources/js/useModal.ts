import { ref } from 'vue'

export interface ModalData {
    [key: string]: unknown
}

const isOpen = ref(false)
const modalData = ref<ModalData>({})

export function openModal(data: ModalData): void {
    modalData.value = data
    isOpen.value = true
}

export function closeModal(): void {
    isOpen.value = false
    modalData.value = {}
}

export function useModal() {
    return { isOpen, modalData, openModal, closeModal }
}
