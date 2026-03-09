package com.payment.app.ui.main

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.payment.app.data.model.NotificationsResponse
import com.payment.app.data.model.SaldoResponse
import com.payment.app.data.repository.AuthRepository
import com.payment.app.data.repository.NotificationRepository
import com.payment.app.data.repository.UserRepository
import com.payment.app.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class MainViewModel @Inject constructor(
    private val userRepository: UserRepository,
    private val notificationRepository: NotificationRepository,
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _saldo = MutableLiveData<Resource<SaldoResponse>>()
    val saldo: LiveData<Resource<SaldoResponse>> = _saldo

    private val _notifications = MutableLiveData<Resource<NotificationsResponse>>()
    val notifications: LiveData<Resource<NotificationsResponse>> = _notifications

    private val _logout = MutableLiveData<Boolean>()
    val logout: LiveData<Boolean> = _logout

    init {
        loadSaldo()
        loadNotifications()
    }

    fun loadSaldo() {
        viewModelScope.launch {
            _saldo.value = Resource.Loading()
            _saldo.value = userRepository.getSaldo()
        }
    }

    fun loadNotifications() {
        viewModelScope.launch {
            _notifications.value = notificationRepository.getNotifications()
        }
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
            _logout.value = true
        }
    }

    fun getUserName(): String = userRepository.getUserName()

    fun isAdmin(): Boolean = userRepository.isAdmin()

    fun refreshData() {
        loadSaldo()
        loadNotifications()
    }
}
