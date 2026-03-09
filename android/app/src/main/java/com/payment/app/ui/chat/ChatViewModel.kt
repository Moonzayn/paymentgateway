package com.payment.app.ui.chat

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.payment.app.data.model.ChatMessage
import com.payment.app.data.repository.ChatRepository
import com.payment.app.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class ChatViewModel @Inject constructor(
    private val chatRepository: ChatRepository
) : ViewModel() {

    private val _messages = MutableLiveData<MutableList<ChatMessage>>(mutableListOf())
    val messages: LiveData<MutableList<ChatMessage>> = _messages

    private val _sendState = MutableLiveData<Resource<String>>()
    val sendState: LiveData<Resource<String>> = _sendState

    private val _unreadCount = MutableLiveData(0)
    val unreadCount: LiveData<Int> = _unreadCount

    private var pollingJob: Job? = null

    init {
        startPolling()
    }

    fun sendMessage(message: String) {
        if (message.isBlank()) return

        viewModelScope.launch {
            _sendState.value = Resource.Loading()
            val result = chatRepository.sendMessage(message)

            when (result) {
                is Resource.Success -> {
                    _sendState.value = Resource.Success("Pesan terkirim")
                    // Refresh messages
                    refreshMessages()
                }
                is Resource.Error -> {
                    _sendState.value = Resource.Error(result.message ?: "Gagal mengirim")
                }
                is Resource.Loading -> {}
            }
        }
    }

    private fun refreshMessages() {
        viewModelScope.launch {
            val lastId = _messages.value?.maxOfOrNull { it.id } ?: 0
            val result = chatRepository.getMessages(lastId)

            if (result is Resource.Success) {
                result.data?.let { response ->
                    val currentList = _messages.value ?: mutableListOf()
                    currentList.addAll(response.messages)
                    _messages.value = currentList
                    _unreadCount.value = response.unreadCount

                    if (response.unreadCount > 0) {
                        chatRepository.markAsRead()
                    }
                }
            }
        }
    }

    fun startPolling() {
        pollingJob?.cancel()
        pollingJob = viewModelScope.launch {
            while (true) {
                delay(3000) // Poll every 3 seconds
                refreshMessages()
            }
        }
    }

    fun stopPolling() {
        pollingJob?.cancel()
        pollingJob = null
    }

    override fun onCleared() {
        super.onCleared()
        stopPolling()
    }
}
