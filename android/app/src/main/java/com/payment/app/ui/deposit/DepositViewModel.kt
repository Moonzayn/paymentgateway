package com.payment.app.ui.deposit

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.payment.app.data.repository.DepositRepository
import com.payment.app.util.Resource
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File
import javax.inject.Inject

@HiltViewModel
class DepositViewModel @Inject constructor(
    private val depositRepository: DepositRepository
) : ViewModel() {

    private val _depositState = MutableLiveData<Resource<String>>()
    val depositState: LiveData<Resource<String>> = _depositState

    fun submitDeposit(nominal: Double, metodeBayar: String, buktiFile: File?) {
        if (nominal < 10000) {
            _depositState.value = Resource.Error("Minimal deposit Rp 10.000")
            return
        }

        viewModelScope.launch {
            _depositState.value = Resource.Loading()
            _depositState.value = depositRepository.submitDeposit(nominal, metodeBayar, buktiFile)
        }
    }
}
