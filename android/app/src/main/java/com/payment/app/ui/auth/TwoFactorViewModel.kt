package com.payment.app.ui.auth

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.payment.app.data.repository.AuthRepository
import com.payment.app.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class TwoFactorViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _verifyState = MutableLiveData<Resource<String>>()
    val verifyState: LiveData<Resource<String>> = _verifyState

    fun verify(code: String) {
        if (code.isBlank() || code.length != 6) {
            _verifyState.value = Resource.Error("Kode harus 6 digit")
            return
        }

        _verifyState.value = Resource.Loading()
        viewModelScope.launch {
            _verifyState.value = authRepository.verify2FA(code)
        }
    }
}
